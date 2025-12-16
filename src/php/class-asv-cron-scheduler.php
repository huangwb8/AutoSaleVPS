<?php
/**
 * Cron scheduler for VPS availability checks.
 *
 * @package AutoSaleVPS
 */

if ( ! class_exists( 'ASV_Cron_Scheduler' ) ) {
	class ASV_Cron_Scheduler {
		/**
		 * Cron hook name.
		 */
		const CRON_HOOK = 'autosalevps_check_vps_availability';

		/**
		 * Repository.
		 *
		 * @var ASV_Config_Repository
		 */
		protected $repository;

		/**
		 * Availability service.
		 *
		 * @var ASV_Availability_Service
		 */
		protected $availability;

		/**
		 * Constructor.
		 *
		 * @param ASV_Config_Repository    $repository  Repository.
		 * @param ASV_Availability_Service $availability Availability service.
		 */
		public function __construct( $repository, $availability ) {
			$this->repository   = $repository;
			$this->availability = $availability;
		}

		/**
		 * Register cron job.
		 */
		public function register() {
			// 注册定时任务钩子
			add_action( self::CRON_HOOK, array( $this, 'execute_vps_checks' ) );

			// 初始化时检查是否需要创建 cron 任务
			add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
		}

		/**
		 * Schedule cron job if not already scheduled.
		 */
		public function maybe_schedule_cron() {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				// 默认每小时检查一次
				wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
			}
		}

		/**
		 * Schedule the cron job.
		 */
		public function schedule_cron() {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
			}
		}

		/**
		 * Unschedule the cron job.
		 */
		public function unschedule_cron() {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
		}

		/**
		 * Execute VPS availability checks.
		 * This is the main cron job callback.
		 */
		public function execute_vps_checks() {
			// 检查是否有 API key
			if ( ! $this->repository->get_api_key() ) {
				$this->log( 'API Key 未配置，跳过 VPS 检查', 'warning' );
				return;
			}

			try {
				// 获取所有 VPS 定义
				$definitions = $this->repository->get_vps_definitions();

				if ( empty( $definitions ) ) {
					$this->log( '没有找到 VPS 定义', 'info' );
					return;
				}

				// 获取模型配置
				$model = $this->repository->get_model();
				$provider = $this->get_provider_config( $model );

				// 批量检查每个 VPS
				$checked_count = 0;
				$updated_count = 0;

				foreach ( $definitions as $definition ) {
					// 检查是否需要更新（基于间隔时间）
					if ( ! $this->should_check_vps( $definition ) ) {
						continue;
					}

					// 检查 VPS 状态
					$result = $this->availability->validate_vps( $definition, $provider );

					// 更新状态
					$key = $definition['vendor'] . '-' . $definition['pid'];
					$statuses = $this->repository->get_statuses();
					$statuses[ $key ] = array(
						'available'  => $result['available'],
						'checked_at' => time(),
						'message'    => $result['message'],
					);

					$this->repository->save_statuses( $statuses );
					$this->repository->update_vps_snapshot_status(
						$definition['vendor'],
						$definition['pid'],
						$result['available'],
						$result['message'],
						$statuses[ $key ]['checked_at']
					);

					$checked_count++;
					$updated_count++;

					// 添加延迟以避免请求过于频繁
					$delay = $definition['valid_delay'] ?? array( 5, 10 );
					$wait_time = isset( $delay[1] ) ? rand( $delay[0], $delay[1] ) : $delay[0];
					if ( $wait_time > 0 ) {
						sleep( $wait_time );
					}
				}

				$this->log( sprintf(
					'VPS 检查完成：检查了 %d 个 VPS，更新了 %d 个状态',
					$checked_count,
					$updated_count
				), 'success' );

			} catch ( Exception $e ) {
				$this->log( sprintf( 'VPS 检查出错：%s', $e->getMessage() ), 'error' );
			}
		}

		/**
		 * Check if a VPS should be checked based on interval.
		 *
		 * @param array $definition VPS definition.
		 * @return bool
		 */
		protected function should_check_vps( $definition ) {
			$vendor = $definition['vendor'];
			$pid = $definition['pid'];
			$interval = $definition['interval'] ?? DAY_IN_SECONDS;

			// 获取最后检查时间
			$statuses = $this->repository->get_statuses();
			$key = $vendor . '-' . $pid;

			if ( ! isset( $statuses[ $key ]['checked_at'] ) ) {
				// 从未检查过，需要检查
				return true;
			}

			$last_checked = $statuses[ $key ]['checked_at'];
			$now = time();

			// 如果距离上次检查已经超过了间隔时间，需要检查
			return ( $now - $last_checked ) >= $interval;
		}

		/**
		 * Get provider config from model.
		 *
		 * @param array $model Model configuration.
		 * @return array
		 */
		protected function get_provider_config( $model ) {
			if ( isset( $model['model_providers'] ) && is_array( $model['model_providers'] ) ) {
				foreach ( $model['model_providers'] as $provider ) {
					return is_array( $provider ) ? $provider : array();
				}
			}
			return array();
		}

		/**
		 * Log message to system log.
		 *
		 * @param string $message Message.
		 * @param string $level   Level (info|error|success|warning).
		 */
		protected function log( $message, $level = 'info' ) {
			$this->repository->append_log( $level, $message );
		}

		/**
		 * Get next scheduled time.
		 *
		 * @return int|false
		 */
		public function get_next_scheduled() {
			return wp_next_scheduled( self::CRON_HOOK );
		}

		/**
		 * Check if cron is active.
		 *
		 * @return bool
		 */
		public function is_cron_active() {
			return (bool) $this->get_next_scheduled();
		}

		/**
		 * Reschedule cron with new interval.
		 *
		 * @param string $interval Cron schedule interval (hourly, twicedaily, daily).
		 */
		public function reschedule_cron( $interval = 'hourly' ) {
			// 先取消现有的
			$this->unschedule_cron();

			// 重新安排
			wp_schedule_event( time(), $interval, self::CRON_HOOK );

			$this->log( sprintf( 'Cron 任务已重新安排，间隔：%s', $interval ), 'success' );
		}
	}
}
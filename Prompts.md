# 业务

基于html/css/js构建一个基于LLM的wordpress插件，可以帮助用户基于自己的aff信息在WordPress博客推广VPS。插件名为`AutoSaleVPS`。必要时你可以用SearXNG插件联网搜索来辅助你的任务。

## 界面

+ 有一个按钮`编辑VPS配置`，点开后会弹出一个编辑窗口，用户可以编辑`config.toml`文件。这个窗口还有`保存配置`按钮，让用户随时保存配置。
+ 有一个按钮`编辑模型配置`，点开后会弹出一个编辑窗口，用户可以编辑`model.toml`文件。这个窗口还有`保存配置`按钮，让用户随时保存配置。

- 有一个按钮`添加KEY`，点开后会弹出一个编辑窗口，要求用户输入一个变量`API_KEY`，它是格式是`sk-xxx`。输入的时候全程是隐藏输入内容的，但旁边有一个眼睛小标签，当用户鼠标移上去后，可以短暂地查看具体的内容；当鼠标移开眼睛小标签立刻内容会隐藏起来，从而保证输入过程的安全。这个窗口还有`保存配置`按钮，让用户随时保存配置。所有由`添加KEY`添加的内容都是高度保密的，不可以泄露，以保证用户财产的安全。
- 有一个按钮`检查可用性`，点击一下后，会检查：
  - 连网功能（与`https://www.racknerd.com/`连接）是否正常
  - LLM模型是否正常工作
- 提供时区选择，默认时区是北京时间。
- 提供一个`日志窗`，上述各种操作都会在这里产生日志，方便用户查看和调试bug。

## 业务逻辑

### 每条VPS记录应该做的事

- 根据`sale_format`类的链接提取VPS的元信息，一般可以通过`<div class="product-info">`找到（当然我不是很确定；如果没有这个标签，需要查看整个网页然后找到相应的信息总结出来，这样就需要用LLM的智能）。类似这种：

```
2.5 GB 内存
2 CPU 核心
45 GB SSD 存储
3000 GB 月流量
1 Gbps 带宽
1个 IPv4 地址
$18.66 /年 (续费同价)
可选机房: 多机房可选
```

这样自动获取的信息在前端里展示，方便用户查看信息。

- 在前端的显眼处展示VPS推广链接（`sale_format`），让用户可以点击。
- 利用LLM的智能，根据`prompt_vps_info`、每款VPS的`human_comment`和VPS的元信息作为输入，生成一段VPS的推广语。

### 每条VPS的推广样式举例

> 由推广链接 + 推广语 + VPS元信息组成。

[推广链接](https://my.racknerd.com/aff.php?aff=4886&pid=924)。RackNerd 2025年黑五优惠活动。价格还是很香的，但配置稍差些。这一款勉强可以玩各种docker应用，对于预算比较紧张的小伙伴可以考虑。

```
2.5 GB 内存
2 CPU 核心
45 GB SSD 存储
3000 GB 月流量
1 Gbps 带宽
1个 IPv4 地址
$18.66 /年 (续费同价)
可选机房: 多机房可选
```

### 验证所有VPS可卖性

- 每次保存`config.toml`时立刻开始验证1次VPS可卖性，每个VPS的验证间隔`valid_vps_time`。每隔`interval_time`的时间验证所有的VPS链接（`valid_format`）。
- 如果发现类似`Oops, there's a problem...`、`Out of Stock`之类的字样，设置参数`VPA_AVAILABLE`为`FALSE`；或者有其它表明VPS已经不再售卖的信息（我也不确定有哪些，因此需要接入LLM的能力（基于`prompt_valid`返回参数`VPA_AVAILABLE`的`TRUE/FALSE`值）帮我判断这个VPS是不是已经卖完），则提示VPS已经卖完。此时，VPS的信息仍然需要显示，但是要有一些其它的前端提示，让用户知道它已经卖完。如果后面测试时VPS又忽然可用了，则重新恢复在线时的样式。如果用户在`config.toml`里删除了该VPS的相关信息，则不再显示该VPS的任何信息。
- 请注意，验证VPS可卖性时千万不要使用`sale_format`，这会增加aff链接的点击，从而降低用户VPS推广的转化率（因为验证过程不可能产生买入），从而影响他的收入。一定要特别注意，只能使用`valid_format`！

## 插件使用


- 所有必须的代码/文件/文件夹在本项目的根目录下生成。用户打包成`.zip`格式，直接在WordPress插件页面上传，就可以在WordPress的后台打开插件的界面。
- 插件有 settings page，这样方便用户在后台进行插件的相关设置和管理。
- 当用户在WordPress页面输入简码`[AutoSaleVPS/]`时，则可以自动在WordPress页面当前位置显示所有VPS相关的前端信息，方便用户展示他的产品。

# VPS商

- 构建一个`config.toml`文件，默认内容如下：

```toml
[aff]
[aff.rn]
4886

[url]
[url.rn]
sale_format = 'https://my.racknerd.com/aff.php?aff={aff}&pid={pid}'
valid_format = 'https://my.racknerd.com/cart.php?a=add&pid={pid}'
valid_interval_time = '172800'
valid_vps_time = '5-10'

[vps]
[vps.rn.923]
pid = '923'
human_comment = '非常基础的一款VPS，但是容量相对来说还是比较大的。'

[vps.rn.924]
pid = '924'
human_comment = ''

[vps.rn.925]
pid = '925'
human_comment = ''

[vps.rn.926]
pid = '926'
human_comment = ''

[vps.rn.927]
pid = '927'
human_comment = ''
```

这里的`rn`代表某个VPS的厂商，用户可能会放多个VPS厂商的产品，因此这种写法可以保证灵活性。以`rn`为例

- `aff.rn`：用户的代理码
- `sale_format`：构建VPS的链接
- `valid_format`：验证VPS的可卖性的链接
- `valid_interval_time`：每隔多久验证所有VPS的可卖性的链接，单位为s。
- `valid_vps_time`：验证所有VPS的可卖性时，每条VPS记录的间隔时间，单位为s。`5-10`意味着随机5-10秒间隔验证1次，每个VPS验证之间都是一个随机数，以减少机器人爬虫的验证特征，降低被目标网站封禁的可能性。

- `vps.rn.pid`类：比如`vps.rn.927`。`human_comment`是用户输入的一个字符，用于辅助生成VPS的推广介绍。后面的数字是pid，每个VPS的pid是唯一的。

# LLM模型

+ 构建一个`model.toml`文件，默认内容如下：

```
[model_providers]
[model_providers.omg]
base_url = 'https://api.ohmygpt.com/v1'
prompt_valid = '基于输入判断VPS是否已经卖完或下架；如果已经卖完或下架，请返回FALSE；否则，请返回TRUE'
prompt_vps_info = '基于输入给出一断推销VPS的广告，30个简体中文。推广要求贴合VPS的实际，不能无脑推，要像一个优秀的VPS推广商那样推广产品。这是一个例子：`RackNerd 2025年黑五优惠活动。中配里的战斗机！算是比较均衡的机型了。这一款可以畅玩各种docker应用。`'
```

这里的模型商omg是一个第三方的模型商，提供类似openai的sdk调用，因此可以用于构建由类openai模型驱动的智能应用。这是一个模型商的模型使用示例（和OpenAI的模型基本上是一样的），帮助你了解如何调用LLM来驱动业务：

```typescript
import OpenAI from 'openai';

const openai = new OpenAI({
    baseURL: 'https://api.ohmygpt.com/v1',
    apiKey: '<OPENAI_API_KEY>',
});

async function main() {
    const completion = await openai.chat.completions.create({
        model: 'gpt-4o',
        messages: [{
            role: 'user',
            content: '生命的意义是什么？',
        },
        ],
    });

    console.log(completion.choices[0].message);
}

main();
```


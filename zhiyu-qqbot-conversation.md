# 知鱼QQ机器人 - 对话记录与操作指南

## 服务器信息
- 服务器路径：`/www/wwwroot/bot.ocoa.cn`
- 静态资源域名：`x.ocoa.cn`（GitHub `oxoax/home` 仓库，Vercel 部署）
- Bot服务：`systemctl restart bot`（systemd管理，端口8080）
- GitHub仓库：`oxoax/zhiyu-qqbot`（私密）

---

## 一、插件列表（`插件/102352380/`）

### 菜单.php
- 指令：`菜单`
- 内联按钮：天气查询 | 漫剪视频，文字找茬 | QQ音乐榜
- 键盘按钮：图片功能 | 娱乐功能，音乐功能 | 点歌功能，知鱼小栈 | 猫粮投喂
- 顶部图片：从 `api/txt/pixiv.txt` 随机获取二次元图

### 天气查询.php
- 指令：`天气查询 城市名`
- 接口：`http://cyapi.top/API/weather.php?city={城市}&n=1&type=text&apikey=7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd`
- 返回文本格式，去掉了详情页链接

### 图片功能.php
- 指令：`图片功能`
- 顶部随机二次元图（pixiv.txt）
- 按钮：二次元图 | 王者皮肤 | 七濑胡桃

### 二次元图.php
- 指令：`二次元图`
- 数据来源：`api/txt/pixiv.txt`（随机一行URL）

### 娱乐功能.php
- 指令：`娱乐功能`
- 顶部随机二次元图（pixiv.txt）
- 按钮布局：
  - 翻塔罗牌 | 今日运势 | 今天吃啥
  - 今日老婆 | Doro结局 | 随机超能力
  - 今日小猪 | 星座运势 | 网易热评
  - 猫妹交流群（单独一行）

### 音乐功能.php
- 指令：`音乐功能`
- 按钮：哈基米 | 王者语音，古风国风 | 欧美音乐，旋律潮流 | 伤感音乐，中文歌曲 | 日韩歌曲
- 音乐类别硬编码：古风国风:39, 欧美音乐:22, 日韩歌曲:14, 伤感音乐:27, 旋律潮流:80, 中文歌曲:49
- 音频URL：`http://x.ocoa.cn/{类别}/{随机数字}.mp3`
- 哈基米：`http://x.ocoa.cn/hjm/{1-52}.amr`

### 点歌功能.php
- 指令：`点歌功能`
- 按钮：QQ点歌 | 网易点歌，酷狗点歌 | 汽水点歌，猫妹交流群

### QQ点歌.php / 网易点歌.php / 酷狗点歌.php / 汽水点歌.php
- 指令：`QQ点歌 歌名` 等
- 接口：`http://cyapi.top/API/qq_music.php?apikey=...&type=json&msg=歌名`
- 图片：`https://x.ocoa.cn/gif/music.gif`（标题），`https://x.ocoa.cn/gif/music1-5.gif`（序号）

### QQ音乐榜.php
- 指令：`QQ音乐榜`
- 榜单接口：`http://cyapi.top/API/music_hot.php?apikey=7c8c8f084709fcb51f3a0c867f1363ff9d71e0650157bcf405cb3539472372bd`
- 歌曲接口：同上 + `&id={list_id}`
- 播放接口：`http://cyapi.top/API/qq_music.php?apikey=...&type=json&mid={song_mid}`
- 流程：榜单列表 → 歌曲列表(前10) → 播放语音

### 今日老婆.php
- 指令：`今日老婆`
- 数据来源：`api/txt/jrlp.txt`
- 格式：`名称-宽-高`，图片URL
- 每日固定（日期+用户ID做种子）

### 今天吃啥.php
- 指令：`今天吃啥`
- 数据来源：`api/txt/jtcs.txt`
- 每日固定

### 今日小猪.php
- 指令：`今日小猪`
- 数据来源：`api/txt/jrxz.txt`（格式：`id|名称|描述|分析`）
- 图片URL：`https://x.ocoa.cn/jrxz/{id}.png`
- 每日固定

### 今日运势.php
- 指令：`今日运势`
- 接口：`https://api.tangdouz.com/wz/luck.php?theme=&return=/?{seed}`
- 每日固定

### Doro结局.php
- 指令：`doro结局`
- 图片URL：`http://x.ocoa.cn/doro/{1-32}.jpg`

### 随机超能力.php
- 指令：`随机超能力`
- 数据来源：`api/cnl.json`

### 漫剪视频.php
- 指令：`漫剪视频`
- 数据来源：`api/txt/mjsp.txt`

### 网易热评.php
- 指令：`网易热评`
- 接口：`http://oiapi.net/api/NeteaseHotReviews`
- 显示：封面、歌曲、歌手、日期、点赞、评论者、评论(content)

### 三角洲每日密码.php
- 指令：`三角洲每日密码`
- 接口：`https://jcy.meiaodai.xyz/api/api/mm.php`
- 显示：名称、密码(引用)、位置(引用)、图片(1373x934/604x859)
- 每个密码点之间用 `---` 分割

### 文字找茬.php
- 指令：`文字找茬`（保留，小游戏相关插件已删除）

### 星座运势.php
- 指令：`星座运势`

---

## 二、txt数据文件（`api/txt/`）

| 文件 | 内容 | 来源 |
|------|------|------|
| `jrlp.txt` | 今日老婆图片URL（名称-宽-高格式） | `x.ocoa.cn/jrlp/` |
| `jtcs.txt` | 今天吃啥图片URL | `x.ocoa.cn/jtcs/` |
| `jrxz.txt` | 今日小猪数据（id\|名称\|描述\|分析） | `x.ocoa.cn/jrxz/` |
| `mjsp.txt` | 漫剪视频URL | - |
| `pixiv.txt` | 二次元图URL | - |

---

## 三、GitHub仓库 `oxoax/home`（静态资源）

域名：`x.ocoa.cn`（Vercel部署）

| 目录 | 内容 |
|------|------|
| `doro/` | Doro结局图片 (1-32.jpg) |
| `hjm/` | 哈基米音频 (1-52.amr) |
| `jrlp/` | 今日老婆图片 |
| `jtcs/` | 今天吃啥图片 |
| `jrxz/` | 今日小猪图片 + pig.json |
| `gif/` | 点歌功能gif (music.gif, music1-9.gif) |
| `中文歌曲/` | 音乐 (1-49.mp3) |
| `古风国风/` | 音乐 (1-39.mp3) |
| `欧美音乐/` | 音乐 (1-22.mp3) |
| `日韩歌曲/` | 音乐 (1-14.mp3) |
| `伤感音乐/` | 音乐 (1-27.mp3) |
| `旋律潮流/` | 音乐 (1-80.mp3) |

---

## 四、服务配置

### bot.service（`/etc/systemd/system/bot.service`）
```ini
[Unit]
Description=QQ Bot
After=network.target

[Service]
Type=simple
WorkingDirectory=/www/wwwroot/bot.ocoa.cn
ExecStart=/usr/bin/php server.php
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

### 常用命令
```bash
systemctl restart bot        # 重启bot
systemctl stop bot           # 停止bot
systemctl status bot         # 查看状态
journalctl -u bot -f         # 查看日志
fuser -k 8080/tcp            # 杀死占用8080端口的进程
php -l 插件/102352380/xxx.php # 检查PHP语法
```

### 端口问题修复
如果启动失败 `bind(127.0.0.1:8080) failed`：
```bash
systemctl stop bot
fuser -k 8080/tcp
systemctl start bot
```

---

## 五、关键规则

1. **每日固定功能**：今日老婆、今日运势、今日小猪、今天吃啥 用 `crc32(date('Y-m-d') . $this->用户ID)` 做种子
2. **图片比例格式**：`![名称 #宽px #高px](URL)`
3. **内联按钮**：`<qqbot-cmd-input text="指令" show="显示文字"/>`
4. **键盘按钮**：在 `$键盘` 数组中定义
5. **Bot重启**：修改插件后必须 `systemctl restart bot`
6. **语法检查**：修改后先 `php -l 文件` 检查语法

---

## 六、GitHub信息

- 源码仓库：`oxoax/zhiyu-qqbot`（私密）
- 静态资源仓库：`oxoax/home`（私密，Vercel部署到x.ocoa.cn）
- 令牌：`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`

---

## 七、已删除的插件

- 小游戏.php
- 找色差.php
- 石头剪刀布.php
- 成语填空.php
- 扫雷.php
- cos图.php
- 王者名称.php

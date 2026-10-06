# CatBotWeb 🐱

基于 PHP 8.4+ 和 Swoole 的高性能 QQ 机器人 Webhook 服务端框架。支持官方 QQ 机器人开放平台 Webhook 事件订阅与 NapCat 适配，内置插件扩展体系与动态管理 API。

---

## ⚡ 一键部署 (推荐)

在全新或已有的 Linux 服务器（Ubuntu / Debian / CentOS / AlmaLinux / RockyLinux）上运行以下一键部署脚本：

```bash
bash <(curl -sSL https://raw.githubusercontent.com/oxoaa/CatBotWeb/main/install.sh)
```

或克隆本仓库后运行：

```bash
git clone https://github.com/oxoaa/CatBotWeb.git
cd CatBotWeb
sudo bash install.sh
```

### 一键脚本自动处理：
- 🔍 **交互索要域名**：自动检测当前服务器公网 IP 与域名 DNS 解析状态；
- ⚙️ **自动环境部署**：自动安装配置 Nginx、PHP 8.4+、Swoole 高性能协程扩展；
- 🔒 **自动化 SSL 证书**：自动申请 Let's Encrypt 免费 HTTPS 证书并设置自动续期；
- 🔄 **Nginx 反向代理**：自动配置反代至 Swoole 后端（8080 端口），支持自定义请求头透传；
- 🛡️ **进程守护与管理**：自动配置 Systemd 服务守护 (`catbot.service`) 与命令行管理工具 `catbot`；
- 🎯 **Webhook 地址输出**：自动生成 Webhook 地址，并提供详细的开放平台绑定指导。

---

## 📌 QQ 机器人开放平台配置指南

部署完成后，脚本会输出您的专属 Webhook 回调地址：
```text
https://<您的域名>/
```

### 绑定步骤：
1. 登录 **[QQ 机器人开放平台](https://q.qq.com/#/apps)**；
2. 进入您的机器人应用管理后台，在左侧导航栏点击 **【开发】** -> **【开发设置】**；
3. 找到 **【回调地址】(Webhook 地址 / 事件订阅 URL)** 输入框；
4. 填入您的完整 Webhook 地址：`https://<您的域名>/`；
5. 点击 **【保存】** 或 **【验证】**：
   - CatBotWeb 服务会自动响应官方的 **Opcode 13 回调挑战**；
   - 页面提示“保存成功 / 验证通过”即表示机器人与 Web 端正式绑定成功！

---

## 🛠️ 快捷运维命令

一键部署完成后，系统内已安装 `catbot` 快捷运维工具：

```bash
# 查看服务运行状态
catbot status

# 重启机器人后端服务
catbot restart

# 停止机器人服务
catbot stop

# 启动机器人服务
catbot start

# 查看实时运行日志
catbot log
```

---

## ⚙️ 配置文件说明 (`config.json`)

配置文件路径：`/opt/CatBotWeb/config.json`（或项目根目录）。

```json
{
  "域名": "0.0.0.0",
  "http端口": 8080,
  "连接池大小": 8,
  "连接超时": 10,
  "超级管理员": [
    "YOUR_ADMIN_OPENID"
  ],
  "框架": {
    "QQBOT": [
      {
        "appid": 102348715,
        "secret": "your_app_secret",
        "sandbox": false
      }
    ],
    "napcat": []
  }
}
```

修改配置后执行 `catbot restart` 重启生效。

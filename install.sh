#!/usr/bin/env bash
# ==============================================================================
# CatBotWeb 一键部署脚本 (QQ 机器人 Webhook 服务端框架)
# 支持环境: Ubuntu / Debian / CentOS / RHEL / AlmaLinux / RockyLinux
# 包含组件: Nginx + PHP 8.4+ + Swoole 扩展 + SSL 证书 + Systemd 服务守护
# ==============================================================================

# 若从管道运行 (如 curl | bash)，将 stdin 重定向至当前终端以支持交互输入
[ -t 0 ] || exec < /dev/tty

set -e

# ==================== 终端颜色定义 ====================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ==================== 辅助打印函数 ====================
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BOLD}${CYAN}==>${NC} ${BOLD}$1${NC}"
}

# ==================== 权限检查 ====================
if [ "$(id -u)" -ne 0 ]; then
    log_error "此脚本必须以 root 权限运行！请使用: sudo bash $0"
    exit 1
fi

# ==================== 系统架构与发行版检测 ====================
OS_TYPE=""
PKG_MANAGER=""

if [ -f /etc/os-release ]; then
    . /etc/os-release
    case "$ID" in
        ubuntu|debian)
            OS_TYPE="debian"
            PKG_MANAGER="apt-get"
            ;;
        centos|rhel|almalinux|rocky)
            OS_TYPE="rhel"
            if command -v dnf >/dev/null 2>&1; then
                PKG_MANAGER="dnf"
            else
                PKG_MANAGER="yum"
            fi
            ;;
        *)
            if [ -n "$ID_LIKE" ]; then
                case "$ID_LIKE" in
                    *debian*|*ubuntu*)
                        OS_TYPE="debian"
                        PKG_MANAGER="apt-get"
                        ;;
                    *rhel*|*fedora*|*centos*)
                        OS_TYPE="rhel"
                        PKG_MANAGER="dnf"
                        ;;
                esac
            fi
            ;;
    esac
fi

if [ -z "$OS_TYPE" ]; then
    log_warn "未识别的 Linux 发行版，将尝试使用通用 Debian/Ubuntu 包管理器运行。"
    OS_TYPE="debian"
    PKG_MANAGER="apt-get"
fi

# ==================== 欢迎 Banner ====================
clear 2>/dev/null || true
echo -e "${CYAN}${BOLD}"
cat << "EOF"
  ____      _   ____        _    __        __   _     
 / ___|__ _| |_| __ )  ___ | |_  \ \      / /__| |__  
| |   / _` | __|  _ \ / _ \| __|  \ \ /\ / / _ \ '_ \ 
| |__| (_| | |_| |_) | (_) | |_    \ V  V /  __/ |_) |
 \____\__,_|\__|____/ \___/ \__|    \_/\_/ \___|_.__/ 
EOF
echo -e "${NC}"
echo -e "${BOLD}欢迎使用 CatBotWeb 一键部署脚本${NC}"
echo -e "本脚本将自动为您安装配置: Nginx、PHP 8.4+、Swoole 高性能扩展、SSL 证书及后台守护进程。"
echo -e "------------------------------------------------------------------------"

# ==================== 用户参数交互索要 ====================
log_step "步骤 1/6: 配置域名与网络"

# 1. 获取当前服务器公网 IP
SERVER_IP=$(curl -s4 --max-time 4 https://api.ipify.org 2>/dev/null || \
           curl -s4 --max-time 4 https://ip.sb 2>/dev/null || \
           curl -s4 --max-time 4 https://ifconfig.me 2>/dev/null || echo "")

if [ -n "$SERVER_IP" ]; then
    log_info "检测到本机公网 IPv4: ${BOLD}${GREEN}${SERVER_IP}${NC}"
else
    log_warn "未能自动获取本机公网 IP，请确保服务器具备公网访问能力。"
fi

# 2. 索要域名并校验
while true; do
    echo -en "${BOLD}请输入您要绑定的域名 (例如: bot.yourdomain.com): ${NC}"
    read -r USER_DOMAIN
    USER_DOMAIN=$(echo "$USER_DOMAIN" | sed -e 's|^https*://||' -e 's|/.*$||' | tr -d '[:space:]')
    
    if [ -z "$USER_DOMAIN" ]; then
        log_warn "域名不能为空，请重新输入！"
        continue
    fi

    # 简单正则校验域名合法性
    if [[ "$USER_DOMAIN" =~ ^[a-zA-Z0-9][-a-zA-Z0-9]*(\.[a-zA-Z0-9][-a-zA-Z0-9]*)+$ ]]; then
        break
    else
        log_warn "域名格式无效，请检查并重新输入！"
    fi
done

# 3. 校验域名 DNS 解析
log_info "正在检测域名 [${USER_DOMAIN}] 的 DNS 解析..."
DOMAIN_IP=$(getent ahosts "$USER_DOMAIN" 2>/dev/null | awk '{print $1}' | head -n 1 || true)
if [ -z "$DOMAIN_IP" ] && command -v ping >/dev/null 2>&1; then
    DOMAIN_IP=$(ping -c 1 "$USER_DOMAIN" 2>/dev/null | sed -n 's/.*(\([0-9.]*\)).*/\1/p' | head -n 1 || true)
fi

if [ -n "$SERVER_IP" ] && [ -n "$DOMAIN_IP" ]; then
    if [ "$SERVER_IP" = "$DOMAIN_IP" ]; then
        log_success "域名解析正常: ${USER_DOMAIN} -> ${SERVER_IP}"
    else
        echo -e "${YELLOW}------------------------------------------------------------------------${NC}"
        log_warn "注意: 域名 [${USER_DOMAIN}] 当前解析 IP [${DOMAIN_IP}] 与本机公网 IP [${SERVER_IP}] 不一致！"
        log_warn "请先前往域名 DNS 控制台将 A 记录解析到本机 IP，否则 Let's Encrypt 证书可能签发失败。"
        echo -e "${YELLOW}------------------------------------------------------------------------${NC}"
        echo -en "${BOLD}是否继续安装？(y/n) [默认: y]: ${NC}"
        read -r CONFIRM
        CONFIRM=${CONFIRM:-y}
        if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
            log_info "安装已取消。请完成 DNS 解析后再试。"
            exit 0
        fi
    fi
elif [ -z "$DOMAIN_IP" ]; then
    log_warn "暂时未能解析到域名 [${USER_DOMAIN}] 的 IP，如果刚添加解析，请稍候同步。"
fi

# 4. 索要机器人信息（可选，允许直接回车跳过）
log_step "步骤 2/6: 配置 QQ 机器人凭证 (可选，回车可跳过后续在后台配置)"
echo -en "${BOLD}请输入 QQ 机器人 AppID (回车跳过): ${NC}"
read -r USER_APPID
USER_APPSECRET=""
USER_ADMIN_ID=""

if [ -n "$USER_APPID" ]; then
    echo -en "${BOLD}请输入 QQ 机器人 AppSecret: ${NC}"
    read -r USER_APPSECRET
    echo -en "${BOLD}请输入机器人超级管理员 OpenID (回车跳过): ${NC}"
    read -r USER_ADMIN_ID
fi

# 5. 确定安装目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
if [ -f "$SCRIPT_DIR/server.php" ] && [ -f "$SCRIPT_DIR/composer.json" ]; then
    INSTALL_DIR="$SCRIPT_DIR"
    log_info "检测到脚本位于源码仓库目录中，直接使用: ${INSTALL_DIR}"
else
    INSTALL_DIR="/opt/CatBotWeb"
    log_info "默认安装目录: ${INSTALL_DIR}"
fi

# ==================== 依赖环境安装 ====================
log_step "步骤 3/6: 安装系统基础依赖与 Nginx"

if [ "$OS_TYPE" = "debian" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y curl wget git unzip ca-certificates lsb-release \
                       gnupg software-properties-common certbot python3-certbot-nginx \
                       nginx socat openssl
elif [ "$OS_TYPE" = "rhel" ]; then
    $PKG_MANAGER update -y
    $PKG_MANAGER install -y epel-release curl wget git unzip certbot python3-certbot-nginx \
                           nginx socat openssl
fi

# ==================== PHP 8.4+ 与 Swoole 环境检查及安装 ====================
log_step "步骤 4/6: 检查并安装 PHP 8.4+ 及 Swoole 扩展"

NEED_INSTALL_PHP=true

if command -v php >/dev/null 2>&1; then
    PHP_VER=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "0")
    HAS_SWOOLE=$(php -r 'echo extension_loaded("swoole") ? "1" : "0";' 2>/dev/null || echo "0")
    
    if php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' 2>/dev/null; then
        if [ "$HAS_SWOOLE" = "1" ]; then
            SWOOLE_VER=$(php -r 'echo phpversion("swoole");' 2>/dev/null || echo "installed")
            log_success "检测到现有环境符合要求: PHP ${PHP_VER} (CLI) + Swoole ${SWOOLE_VER}，跳过编译！"
            NEED_INSTALL_PHP=false
        else
            log_info "检测到 PHP ${PHP_VER} 符合版本要求，但缺少 Swoole 扩展，将尝试单独安装 Swoole。"
        fi
    else
        log_info "当前 PHP 版本 (${PHP_VER}) 低于 8.4，正在升级安装 PHP 8.4+..."
    fi
fi

if [ "$NEED_INSTALL_PHP" = true ]; then
    if [ "$OS_TYPE" = "debian" ]; then
        if [ "$ID" = "ubuntu" ]; then
            log_info "正在配置 Ubuntu PPA (ppa:ondrej/php)..."
            add-apt-repository -y ppa:ondrej/php || true
            apt-get update -y
        else
            log_info "正在配置 Debian PHP 源 (packages.sury.org)..."
            curl -sSLo /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg || true
            echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
            apt-get update -y
        fi

        log_info "正在安装 PHP 8.4 及必备扩展组件..."
        apt-get install -y php8.4-cli php8.4-common php8.4-curl php8.4-mbstring \
                           php8.4-xml php8.4-zip php8.4-opcache php8.4-dev \
                           php8.4-sqlite3 php-pear || true

        # 尝试直接安装包或通过 pecl 安装 swoole
        if ! php -r 'exit(extension_loaded("swoole") ? 0 : 1);' 2>/dev/null; then
            log_info "正在安装 Swoole 扩展 (可通过 prebuilt 或 pecl)..."
            apt-get install -y php8.4-swoole 2>/dev/null || true
            
            if ! php -r 'exit(extension_loaded("swoole") ? 0 : 1);' 2>/dev/null; then
                apt-get install -y build-essential libssl-dev libcurl4-openssl-dev pkg-config || true
                pecl channel-update pecl.php.net || true
                printf "\n\n\n\n\n" | pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-http2="yes"' swoole || true
                echo "extension=swoole.so" > /etc/php/8.4/mods-available/swoole.ini 2>/dev/null || true
                phpenmod swoole 2>/dev/null || true
            fi
        fi

    elif [ "$OS_TYPE" = "rhel" ]; then
        log_info "正在配置 RHEL/CentOS Remi 源..."
        $PKG_MANAGER install -y https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm || true
        $PKG_MANAGER module reset php -y || true
        $PKG_MANAGER module enable php:remi-8.4 -y || true
        $PKG_MANAGER install -y php-cli php-common php-mbstring php-curl php-xml \
                                php-zip php-devel php-opcache php-pear gcc make || true

        if ! php -r 'exit(extension_loaded("swoole") ? 0 : 1);' 2>/dev/null; then
            printf "\n\n\n\n\n" | pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-http2="yes"' swoole || true
            echo "extension=swoole.so" > /etc/php.d/20-swoole.ini
        fi
    fi
fi

# 最终验证 PHP 与 Swoole
PHP_BIN=$(which php || echo "/usr/bin/php")
if ! $PHP_BIN -v >/dev/null 2>&1; then
    log_error "PHP 安装失败，请检查系统网络及软件源！"
    exit 1
fi
if ! $PHP_BIN -r 'exit(extension_loaded("swoole") ? 0 : 1);' 2>/dev/null; then
    log_error "Swoole 扩展安装未生效！请手动安装 swoole 扩展后重试。"
    exit 1
fi
log_success "PHP 环境与 Swoole 扩展已就绪: $($PHP_BIN -v | head -n1)"

# ==================== 代码拉取与配置 ====================
log_step "步骤 5/6: 部署 CatBotWeb 源码与配置"

REPO_URL="https://github.com/oxoaa/CatBotWeb.git"

if [ "$INSTALL_DIR" != "$SCRIPT_DIR" ]; then
    if [ ! -d "$INSTALL_DIR/.git" ]; then
        log_info "正在从 GitHub 克隆源码仓库 [${REPO_URL}] 到 ${INSTALL_DIR}..."
        mkdir -p "$INSTALL_DIR"
        
        # 尝试常规克隆
        if ! git clone "$REPO_URL" "$INSTALL_DIR" 2>/dev/null; then
            log_warn "直接克隆失败（可能为私有仓库或网络受限）。"
            echo -en "${BOLD}请输入 GitHub Personal Access Token (PAT，直接回车跳过): ${NC}"
            read -r GITHUB_TOKEN
            if [ -n "$GITHUB_TOKEN" ]; then
                git clone "https://${GITHUB_TOKEN}@github.com/oxoaa/CatBotWeb.git" "$INSTALL_DIR"
            else
                log_error "克隆失败！请确认仓库访问权限或手动放置源码在 ${INSTALL_DIR}。"
                exit 1
            fi
        fi
    else
        log_info "检测到已有仓库，正在拉取最新代码..."
        git -C "$INSTALL_DIR" pull origin main || true
    fi
fi

cd "$INSTALL_DIR"

# 确保运行时目录结构与权限
mkdir -p "$INSTALL_DIR/数据" "$INSTALL_DIR/插件" "$INSTALL_DIR/插件仓库" "$INSTALL_DIR/日志"
chmod -R 755 "$INSTALL_DIR"

# 生成/补全自动加载文件 (vendor/autoload.php)
if [ ! -f "$INSTALL_DIR/vendor/autoload.php" ]; then
    log_info "生成 PSR-4 轻量自动加载器..."
    mkdir -p "$INSTALL_DIR/vendor"
    cat > "$INSTALL_DIR/vendor/autoload.php" << 'EOF'
<?php
// CatBot PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'ShengBot\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
if (file_exists(__DIR__ . '/../src/Protobuf/helpers.php')) {
    require_once __DIR__ . '/../src/Protobuf/helpers.php';
}
EOF
fi

# 初始化/配置 config.json
if [ ! -f "$INSTALL_DIR/config.json" ]; then
    log_info "生成初始 config.json..."
    cat > "$INSTALL_DIR/config.json" << EOF
{
  "域名": "0.0.0.0",
  "http端口": 8080,
  "连接池大小": 8,
  "连接超时": 10,
  "超级管理员": [],
  "框架": {
    "QQBOT": [],
    "napcat": []
  }
}
EOF
fi

# 如果用户在命令行输入了凭证，写入 config.json
if [ -n "$USER_APPID" ] && [ -n "$USER_APPSECRET" ]; then
    log_info "写入用户填写的机器人配置..."
    $PHP_BIN -r "
        \$f = '$INSTALL_DIR/config.json';
        \$cfg = json_decode(file_get_contents(\$f), true) ?? [];
        \$cfg['域名'] = '0.0.0.0';
        \$cfg['http端口'] = 8080;
        if (!empty('$USER_ADMIN_ID')) {
            \$cfg['超级管理员'] = array_values(array_unique(array_merge(\$cfg['超级管理员'] ?? [], ['$USER_ADMIN_ID'])));
        }
        \$bots = &\$cfg['框架']['QQBOT'];
        if (!is_array(\$bots)) \$bots = [];
        \$found = false;
        foreach (\$bots as &\$b) {
            if ((string)\$b['appid'] === '$USER_APPID') {
                \$b['secret'] = '$USER_APPSECRET';
                \$b['sandbox'] = false;
                \$found = true;
                break;
            }
        }
        if (!\$found) {
            \$bots[] = ['appid' => (int)'$USER_APPID', 'secret' => '$USER_APPSECRET', 'sandbox' => false];
        }
        file_put_contents(\$f, json_encode(\$cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    "
fi

# ==================== SSL 证书与 Nginx 反向代理配置 ====================
log_step "步骤 6/6: 配置 SSL 证书、Nginx 与 Systemd 守护进程"

CERT_DIR="/etc/letsencrypt/live/$USER_DOMAIN"
mkdir -p "$CERT_DIR"

# 1. 若无证书，先生成自签名临时证书，确保 Nginx 可平滑启动
if [ ! -f "$CERT_DIR/fullchain.pem" ] || [ ! -f "$CERT_DIR/privkey.pem" ]; then
    log_info "生成临时 SSL 证书以初始化 Nginx..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$CERT_DIR/privkey.pem" \
        -out "$CERT_DIR/fullchain.pem" \
        -subj "/CN=$USER_DOMAIN" 2>/dev/null || true
fi

# 2. 写入 Nginx 配置文件
mkdir -p /var/www/html
NGINX_CONF_DIR="/etc/nginx/sites-available"
NGINX_ENABLED_DIR="/etc/nginx/sites-enabled"

if [ ! -d "$NGINX_CONF_DIR" ]; then
    NGINX_CONF_DIR="/etc/nginx/conf.d"
    NGINX_ENABLED_DIR="/etc/nginx/conf.d"
fi

NGINX_CONF="$NGINX_CONF_DIR/${USER_DOMAIN}.conf"

log_info "写入 Nginx 反向代理配置 [${NGINX_CONF}]..."
cat > "$NGINX_CONF" << EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${USER_DOMAIN};

    # ACME 证书校验路径
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }

    # HTTP 强制跳转 HTTPS
    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${USER_DOMAIN};

    ssl_certificate ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # 关键: 允许下划线请求头 (QQ Bot 依赖 x-bot-appid 等)
    underscores_in_headers on;

    # 所有请求转发至 CatBot Swoole 服务 (8080 端口)
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
EOF

if [ "$NGINX_CONF_DIR" != "$NGINX_ENABLED_DIR" ]; then
    ln -sf "$NGINX_CONF" "$NGINX_ENABLED_DIR/${USER_DOMAIN}.conf"
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
fi

# 测试并重载 Nginx
nginx -t
systemctl restart nginx || systemctl reload nginx

# 3. 尝试申请正式 Let's Encrypt 证书
log_info "正在申请 Let's Encrypt 官方免费 SSL 证书..."
if certbot certonly --webroot -w /var/www/html -d "$USER_DOMAIN" \
   --non-interactive --agree-tos --register-unsafely-without-email \
   --keep-until-expiring 2>/dev/null; then
    log_success "Let's Encrypt SSL 证书申请成功！已自动加载正式证书。"
    systemctl reload nginx
else
    log_warn "Let's Encrypt 自动签发暂未通过（通常由于域名 DNS 尚未完全生效或 80 端口受限）。"
    log_warn "已启用备用证书保证服务运行。待 DNS 生效后只需运行: certbot --nginx -d $USER_DOMAIN 即可更新证书。"
fi

# 4. 配置 Systemd 守护服务
log_info "配置 Systemd 进程守护 (catbot.service)..."
cat > /etc/systemd/system/catbot.service << EOF
[Unit]
Description=CatBotWeb QQ Bot Server (Swoole)
After=network.target nginx.service

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}
ExecStart=${PHP_BIN} ${INSTALL_DIR}/server.php
Restart=always
RestartSec=3
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable catbot
systemctl restart catbot

# 5. 状态检查
sleep 2
if systemctl is-active --quiet catbot; then
    log_success "CatBotWeb 后台服务运行中！"
else
    log_warn "服务启动中，可通过: systemctl status catbot 查看状态。"
fi

# 6. 安装快捷管理命令行工具
cat > /usr/local/bin/catbot << 'EOF'
#!/usr/bin/env bash
case "$1" in
    status)
        systemctl status catbot
        ;;
    start)
        systemctl start catbot
        echo "CatBot 已启动"
        ;;
    stop)
        systemctl stop catbot
        echo "CatBot 已停止"
        ;;
    restart)
        systemctl restart catbot
        echo "CatBot 已重启"
        ;;
    log)
        journalctl -u catbot -f -n 50
        ;;
    *)
        echo "CatBot 命令行管理工具"
        echo "用法: catbot {start|stop|restart|status|log}"
        ;;
esac
EOF
chmod +x /usr/local/bin/catbot

# ==================== 最终成果输出与引导 ====================
WEBHOOK_URL="https://${USER_DOMAIN}/"

echo -e "\n${GREEN}${BOLD}========================================================================${NC}"
echo -e "${GREEN}${BOLD}🎉 恭喜！CatBotWeb 一键部署圆满完成！${NC}"
echo -e "${GREEN}${BOLD}========================================================================${NC}"

echo -e "\n${BOLD}【服务运行状态】${NC}"
echo -e " • 绑定域名:      ${CYAN}https://${USER_DOMAIN}${NC}"
echo -e " • 本地服务端口:  127.0.0.1:8080 (由 Nginx 反向代理)"
echo -e " • 部署目录:      ${INSTALL_DIR}"
echo -e " • 快捷运维命令:  ${YELLOW}catbot status${NC} / ${YELLOW}catbot restart${NC} / ${YELLOW}catbot log${NC}"

echo -e "\n${PURPLE}${BOLD}========================================================================${NC}"
echo -e "${YELLOW}${BOLD}🔥【最重要一步】前往 QQ 机器人开放平台配置并绑定 Webhook${NC}"
echo -e "${PURPLE}${BOLD}========================================================================${NC}"
echo -e "${BOLD}您的 Webhook 回调地址为：${NC}"
echo -e "👉  ${GREEN}${BOLD}${WEBHOOK_URL}${NC}  👈\n"
echo -e "${BOLD}配置与绑定步骤如下：${NC}"
echo -e "  1. 打开 QQ 机器人开放平台 (开发者平台)：${CYAN}https://q.qq.com/#/apps${NC}"
echo -e "  2. 登录并选择您的机器人应用，点击左侧菜单【开发】->【开发设置】"
echo -e "  3. 找到【回调地址】(Webhook 地址 / 事件订阅地址) 输入框"
echo -e "  4. 将上述地址完整粘贴进去: ${GREEN}${BOLD}${WEBHOOK_URL}${NC}"
echo -e "  5. 点击【保存】或【验证】："
echo -e "     • 我们的 CatBotWeb 服务会自动响应官方的 Opcode 13 回调挑战；"
echo -e "     • 开放平台提示“保存成功 / 验证通过”即表示机器人已成功绑定上线！\n"

if [ -z "$USER_APPID" ]; then
echo -e "${YELLOW}提示: 您刚才跳过了输入 AppID/Secret，请在开放平台获取后，编辑配置文件填写:${NC}"
echo -e "  编辑命令:  nano ${INSTALL_DIR}/config.json"
echo -e "  重启生效:  catbot restart\n"
fi

echo -e "${GREEN}${BOLD}========================================================================${NC}\n"

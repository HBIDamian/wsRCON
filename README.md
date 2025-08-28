# WebSocket Console Plugin for PocketMine-MP

A complete WebSocket implementation for remote server console access with **zero external dependencies**.

## 🚀 Features

- 🔐 **Password Authentication** - Secure your console access
- 📡 **Real-time Communication** - Instant command execution and responses
- 🎯 **Event Broadcasting** - Player joins, quits, chat, deaths, and server commands
- 👥 **Multiple Clients** - Support for concurrent WebSocket connections
- ⚙️ **Configurable** - Easy setup via config.yml
- 🛡️ **Session Management** - Secure authentication per connection
- 📝 **Debug Logging** - Optional detailed logging for troubleshooting
- 🔧 **Pure PHP** - No external libraries or Composer packages required


## Online interface:

I've made an interface for this plugin, that can be found over at [https://hbidamian.github.io/wsRCON/](https://hbidamian.github.io/wsRCON/).

If you want to self host your own interface, you can grab it from [https://github.com/HBIDamian/wsRCON/tree/webconsole](https://github.com/HBIDamian/wsRCON/tree/webconsole)


## ⚙️ Configuration

After the first startup, the plugin will create a config file at:
`plugins/wsRCON/config.yml`

Edit this file to customize your settings:

```yaml
#═══════════════════════════════════════════════════════════════════════════════
#                         WebSocket Console Configuration
#═══════════════════════════════════════════════════════════════════════════════

# 🔐 Authentication Password
# This will be automatically generated on first run for security
websocket-password: "change_this_password_123"

# 🌐 Server Port
# Set to "default" (with quotations) to use the server's current port, or specify a port number (Ex: 19132)
websocket-port: "default"

# 🏠 Server Host
# Default: 0.0.0.0 for all interfaces
websocket-host: "0.0.0.0"

# 🐛 Debug Mode
# Enable debug logging (useful for troubleshooting)
debug-logging: false

# 👥 Connection Limit
# Maximum number of concurrent WebSocket connections
max-connections: 10

#═══════════════════════════════════════════════════════════════════════════════
#                                   Notes
#═══════════════════════════════════════════════════════════════════════════════

# ⚠️  IMPORTANT: Restart your PocketMine server after changing this configuration

#───────────────────────────────────────────────────────────────────────────────
#                              Password Examples
#───────────────────────────────────────────────────────────────────────────────

# 💡 Strong password examples:
# websocket-password: "Random_Words-789"
# websocket-password: "WebSocket@Admin#2025"
# websocket-password: "WA%5e6vPF3JHL!"

# ⚠️  For no authentication (SECURITY RISK):
# websocket-password: ""


```


## 🌐 Connecting


## 📡 Supported Events

The plugin broadcasts the following server events to all authenticated clients:

- 👋 **Player Join/Quit** - When players connect or disconnect
- 💬 **Player Chat** - All chat messages
- 💀 **Player Deaths** - Death messages
- ⌨️ **Console Commands** - Commands executed in server console



## 🛠️ Troubleshooting

### Plugin Won't Start

1. **Port in use**: Change `websocket-port` in config.yml
2. **Permission denied**: Check server file permissions
3. **Firewall**: Ensure the port is open

### Can't Connect

1. **Wrong host/port**: Verify connection settings
2. **Firewall**: Check if port is blocked
3. **Authentication**: Verify password is correct

### Commands Not Working

1. **Not authenticated**: Check password
2. **Insufficient permissions**: Plugin runs with OP permissions
3. **Invalid command**: Use `help` to see available commands

## 📊 Debug Mode

This is meant for troubleshooting and development purposes. You most likely need to use it unless you like to tinker with the code directly

```yaml
debug-logging: true
```

This will log:
- Connection attempts
- Authentication events
- Message broadcasts
- Client disconnections


## 🤝 Contributing

This plugin is open source and contributions are welcome! Or if you have a better implementation idea, feel free to share it, and overtake this plugin with your own!

## 📄 License

Apache License 2.0 - See LICENSE file for details.

---

**Made with ❤️ for the PocketMine community**

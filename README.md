# wcRCON - For PocketMine-MP Servers

A simple HTML/CSS/JavaScript WebSocket console interface for PocketMine-MP servers.

## Features

- Direct WebSocket connection to PocketMine server
- Real-time console output display
- Command execution interface
- Quick command buttons
- Connection settings management
- Console history and export
- Auto-scroll functionality
- Command history navigation

## Usage

1. **Open the website**: Simply open `index.html` in any modern web browser that supports WebSockets.

2. **Configure connection**: 
   - Enter your PocketMine server's WebSocket host (e.g., `play.example.com`)
   - Enter the WebSocket port (e.g., `19100`)
   - Enter the WebSocket password if required
   - Click "Connect" to establish connection

## Server Requirements

Your PocketMine-MP server must have:
- WebSocket plugin enabled
- WebSocket port configured and accessible
- Proper authentication setup (if using password)

## Browser Compatibility

This console works with any modern web browser that supports:
- WebSockets
- Local Storage
- ES6 JavaScript features

## Configuration Storage

Connection settings are automatically saved to your browser's local storage and will be restored when you reload the page.

## Files

- `index.html` - Main webpage
- `script.js` - WebSocket console functionality
- `style.css` - Styling and layout
- `README.md` - This documentation

## No Server Required

This is now a pure client-side application. No Node.js server or dependencies are needed - just open the HTML file in your browser!

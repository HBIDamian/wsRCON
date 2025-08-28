// WebSocket Console JavaScript
class PocketMineConsole {
    constructor() {
        this.socket = null;
        this.autoScroll = true;
        this.commandHistory = [];
        this.historyIndex = -1;
        this.reconnectInterval = null;
        this.consoleHistory = [];
        
        // Default configuration
        this.config = {
            pocketmineHost: '',
            pocketminePort: '',
            pocketminePassword: '',
            maxConsoleLines: 1000
        };
        
        this.setupEventListeners();
        this.setupModalEventListeners();
        this.loadConfiguration();
        
        // Set initial disconnected state
        this.updateConnectionStatus(false, 'Not Connected');
        
        // Always show modal on page load - user must provide server info
        this.showConnectionModal();
        
        // Add initial system message
        this.addConsoleMessage('SYSTEM', 'Please configure server connection to get started', 'welcome');
    }
    
    connectToServer() {
        try {
            // Validate configuration before connecting
            if (!this.config.pocketmineHost || !this.config.pocketminePort) {
                this.addConsoleMessage('SYSTEM', 'Server configuration required. Please set host and port.', 'error');
                this.updateConnectionStatus(false, 'Configuration required');
                return;
            }
            
            if (this.socket && this.socket.readyState === WebSocket.OPEN) {
                this.socket.close();
            }
            
            const wsUrl = `ws://${this.config.pocketmineHost}:${this.config.pocketminePort}`;
            this.addConsoleMessage('SYSTEM', `Connecting to ${wsUrl}...`, 'welcome');
            
            this.socket = new WebSocket(wsUrl);
            
            this.socket.onopen = () => {
                this.addConsoleMessage('SYSTEM', 'Connected to PocketMine server', 'welcome');
                this.updateConnectionStatus(true, 'Connected to PocketMine server');
                
                // Send authentication if password is provided
                if (this.config.pocketminePassword && this.config.pocketminePassword.trim() !== '') {
                    const authMessage = JSON.stringify({
                        type: 'auth',
                        password: this.config.pocketminePassword
                    });
                    this.socket.send(authMessage);
                }
                
                // Clear reconnect interval if connection is successful
                if (this.reconnectInterval) {
                    clearInterval(this.reconnectInterval);
                    this.reconnectInterval = null;
                }
            };
            
            this.socket.onmessage = (event) => {
                try {
                    const message = event.data;
                    let logEntry;
                    
                    // Try to parse as JSON first
                    try {
                        const jsonData = JSON.parse(message);
                        if (jsonData.type === 'console') {
                            logEntry = {
                                timestamp: jsonData.timestamp || this.getCurrentTimestamp(),
                                message: jsonData.message,
                                type: 'console'
                            };
                        } else if (jsonData.type === 'response') {
                            logEntry = {
                                timestamp: jsonData.timestamp || this.getCurrentTimestamp(),
                                message: jsonData.message,
                                type: 'response'
                            };
                        } else {
                            logEntry = {
                                timestamp: this.getCurrentTimestamp(),
                                message: message,
                                type: 'server'
                            };
                        }
                    } catch (parseError) {
                        // If not JSON, treat as raw server message
                        logEntry = {
                            timestamp: this.getCurrentTimestamp(),
                            message: message,
                            type: 'server'
                        };
                    }
                    
                    // Add to console history
                    this.consoleHistory.push(logEntry);
                    
                    // Keep only the last maxConsoleLines entries
                    if (this.consoleHistory.length > this.config.maxConsoleLines) {
                        this.consoleHistory = this.consoleHistory.slice(-this.config.maxConsoleLines);
                    }
                    
                    this.addConsoleMessage(logEntry.timestamp, logEntry.message, logEntry.type);
                    
                } catch (error) {
                    console.error('Error processing message from PocketMine:', error);
                }
            };
            
            this.socket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.updateConnectionStatus(false, `Connection error: ${error.message || 'Connection failed'}`);
                this.addConsoleMessage('ERROR', `Connection failed. Please check your server settings.`, 'error');
                
                // Reopen modal on connection error to allow user to fix settings
                setTimeout(() => {
                    this.showConnectionModal('Connection failed');
                }, 1000); // Small delay to let user see the error message
            };
            
            this.socket.onclose = (event) => {
                if (event.wasClean) {
                    this.addConsoleMessage('SYSTEM', 'Connection closed', 'welcome');
                    this.updateConnectionStatus(false, 'Disconnected');
                } else {
                    // Unexpected disconnection - likely connection error
                    this.addConsoleMessage('ERROR', 'Connection lost. Please reconnect.', 'error');
                    this.updateConnectionStatus(false, 'Connection lost');
                    
                    // Reopen modal on unexpected disconnection
                    setTimeout(() => {
                        this.showConnectionModal('Connection lost');
                    }, 1000);
                }
            };
            
        } catch (error) {
            console.error('Failed to connect to PocketMine server:', error);
            this.addConsoleMessage('ERROR', `Failed to connect: ${error.message}`, 'error');
            this.updateConnectionStatus(false, 'Connection failed');
            
            // Reopen modal on connection failure
            setTimeout(() => {
                this.showConnectionModal('Failed to connect');
            }, 1000);
        }
    }
    
    stopReconnection() {
        if (this.reconnectInterval) {
            clearInterval(this.reconnectInterval);
            this.reconnectInterval = null;
            this.addConsoleMessage('SYSTEM', 'Reconnection attempts stopped', 'welcome');
        }
    }
    
    disconnectFromServer() {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.addConsoleMessage('SYSTEM', 'Disconnecting from server...', 'welcome');
            this.socket.close();
        } else {
            this.addConsoleMessage('SYSTEM', 'Already disconnected', 'welcome');
            this.updateConnectionStatus(false, 'Not Connected');
        }
    }
    
    disconnectAndClearData() {
        // First disconnect
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.close();
        }
        
        // Clear localStorage
        try {
            localStorage.removeItem('pocketmine-config');
            this.addConsoleMessage('SYSTEM', 'Disconnected and cleared saved data', 'welcome');
        } catch (error) {
            this.addConsoleMessage('ERROR', 'Failed to clear saved data', 'error');
        }
        
        // Reset configuration to defaults
        this.config = {
            pocketmineHost: '',
            pocketminePort: '',
            pocketminePassword: '',
            maxConsoleLines: 1000
        };
        
        // Update connection status
        this.updateConnectionStatus(false, 'Not Connected');
        
        // Show the connection modal for fresh setup
        setTimeout(() => {
            this.showConnectionModal();
        }, 500);
    }
    
    getCurrentTimestamp() {
        const now = new Date();
        return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
    }
    
    setupEventListeners() {
        const commandInput = document.getElementById('command-input');
        const sendButton = document.getElementById('send-button');
        const clearButton = document.getElementById('clear-console');
        const autoScrollToggle = document.getElementById('auto-scroll-toggle');
        const exportButton = document.getElementById('export-logs');
        const connectButton = document.getElementById('connect-button');
        const connectButtonConnected = document.getElementById('connect-button-connected');
        const disconnectButton = document.getElementById('disconnect-button');
        const disconnectClearButton = document.getElementById('disconnect-clear-button');
        const hostInput = document.getElementById('host-input');
        const portInput = document.getElementById('port-input');
        const passwordInput = document.getElementById('password-input');
        
        // Command input handling
        commandInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                this.sendCommand();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateHistory(-1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateHistory(1);
            }
        });
        
        sendButton.addEventListener('click', () => {
            this.sendCommand();
        });
        
        // Quick commands
        document.querySelectorAll('.quick-cmd').forEach(button => {
            button.addEventListener('click', () => {
                const command = button.getAttribute('data-command');
                commandInput.value = command;
                this.sendCommand();
            });
        });
        
        // Console controls
        clearButton.addEventListener('click', () => {
            this.clearConsole();
        });
        
        autoScrollToggle.addEventListener('click', () => {
            this.toggleAutoScroll();
        });
        
        exportButton.addEventListener('click', () => {
            this.exportLogs();
        });
        
        // Connection controls
        connectButton.addEventListener('click', () => {
            this.showConnectionModal();
        });
        
        // Connect button when connected (same functionality)
        connectButtonConnected.addEventListener('click', () => {
            this.showConnectionModal();
        });
        
        // Disconnect button
        disconnectButton.addEventListener('click', () => {
            this.disconnectFromServer();
        });
        
        // Disconnect and clear data button
        disconnectClearButton.addEventListener('click', () => {
            this.disconnectAndClearData();
        });

        // Auto-focus command input
        commandInput.focus();
        
        // Focus command input when clicking in console
        document.getElementById('console-output').addEventListener('click', () => {
            commandInput.focus();
        });
    }

    setupModalEventListeners() {
        const modalConnectBtn = document.getElementById('modal-connect-btn');
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        const modalTogglePassword = document.getElementById('modal-toggle-password');
        const modalPasswordInput = document.getElementById('modal-password-input');
        const modalHostInput = document.getElementById('modal-host-input');
        const modalPortInput = document.getElementById('modal-port-input');

        // Connect button
        modalConnectBtn.addEventListener('click', () => {
            this.handleModalConnect();
        });

        // Cancel button
        modalCancelBtn.addEventListener('click', () => {
            this.hideConnectionModal();
        });

        // Password toggle
        modalTogglePassword.addEventListener('click', () => {
            this.toggleModalPasswordVisibility();
        });

        // Enter key to connect
        [modalHostInput, modalPortInput, modalPasswordInput].forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    this.handleModalConnect();
                }
            });
        });

        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('connection-modal');
                if (!modal.classList.contains('hidden')) {
                    this.hideConnectionModal();
                }
            }
        });
    }

    showConnectionModal(errorMessage = null) {
        const modal = document.getElementById('connection-modal');
        modal.classList.remove('hidden');
        
        // Update modal title based on context
        const modalTitle = modal.querySelector('h2');
        const modalSubtext = modal.querySelector('p');
        
        if (errorMessage) {
            modalTitle.textContent = '🔴 Connection Failed';
            modalSubtext.textContent = 'Please check your server details and try again';
            modalSubtext.className = 'text-red-400 text-sm text-center mb-4';
        } else {
            modalTitle.textContent = '🎮 Connect to PocketMine Server';
            modalSubtext.textContent = 'Enter your server details to connect';
            modalSubtext.className = 'text-github-text-muted text-sm text-center mb-4';
        }
        
        // Load saved values or defaults
        document.getElementById('modal-host-input').value = this.config.pocketmineHost || '';
        document.getElementById('modal-port-input').value = this.config.pocketminePort || '';
        document.getElementById('modal-password-input').value = this.config.pocketminePassword || '';
        
        // Focus the first input
        document.getElementById('modal-host-input').focus();
    }

    hideConnectionModal() {
        const modal = document.getElementById('connection-modal');
        modal.classList.add('hidden');
    }

    handleModalConnect() {
        const host = document.getElementById('modal-host-input').value.trim();
        const port = parseInt(document.getElementById('modal-port-input').value);
        const password = document.getElementById('modal-password-input').value;

        if (!host) {
            alert('Please enter a host');
            return;
        }

        if (!port || port < 1 || port > 65535) {
            alert('Please enter a valid port (1-65535)');
            return;
        }

        // Update configuration
        this.config.pocketmineHost = host;
        this.config.pocketminePort = port;
        this.config.pocketminePassword = password;

        // Update sidebar inputs
        document.getElementById('host-input').value = host;
        document.getElementById('port-input').value = port;

        // Update status display
        document.getElementById('current-host').textContent = host;
        document.getElementById('current-port').textContent = port;

        // Save configuration
        this.saveConfiguration();

        // Hide modal and connect
        this.hideConnectionModal();
        this.connectToServer();
    }

    toggleModalPasswordVisibility() {
        const passwordInput = document.getElementById('modal-password-input');
        const toggleButton = document.getElementById('modal-toggle-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.textContent = '🙈';
        } else {
            passwordInput.type = 'password';
            toggleButton.textContent = '👁️';
        }
    }
    
    loadConfiguration() {
        // Load configuration from localStorage or use defaults
        const savedConfig = localStorage.getItem('pocketmine-config');
        if (savedConfig) {
            try {
                const config = JSON.parse(savedConfig);
                this.config = { ...this.config, ...config };
            } catch (error) {
                console.error('Failed to parse saved configuration:', error);
            }
        }
        
        // Don't update UI here - only show server settings when connected
    }
    
    saveConfiguration() {
        try {
            localStorage.setItem('pocketmine-config', JSON.stringify(this.config));
        } catch (error) {
            console.error('Failed to save configuration:', error);
        }
    }
    
    updateConnectionStatus(connected, message) {
        const statusElement = document.getElementById('connection-status');
        const statusText = document.getElementById('status-text');
        const commandInput = document.getElementById('command-input');
        const sendButton = document.getElementById('send-button');
        
        // Update status element classes for Tailwind
        statusElement.className = connected ? 'flex items-center gap-2 status-connected' : 'flex items-center gap-2 status-disconnected';
        statusText.textContent = message;
        
        commandInput.disabled = !connected;
        sendButton.disabled = !connected;
        
        // Update quick command buttons
        document.querySelectorAll('.quick-cmd').forEach(button => {
            button.disabled = !connected;
        });
        
        // Update server settings visibility
        this.updateServerSettingsVisibility(connected);
    }
    
    updateServerSettingsVisibility(connected) {
        const settingsForm = document.getElementById('server-settings-form');
        const settingsPlaceholder = document.getElementById('server-settings-placeholder');
        
        if (connected) {
            // Show server settings with current connection info
            settingsForm.classList.remove('hidden');
            settingsPlaceholder.classList.add('hidden');
            
            // Update the form with current connection details
            document.getElementById('host-input').value = this.config.pocketmineHost;
            document.getElementById('port-input').value = this.config.pocketminePort;
            
            // Update server info display
            document.getElementById('current-host').textContent = this.config.pocketmineHost;
            document.getElementById('current-port').textContent = this.config.pocketminePort;
        } else {
            // Hide server settings and show placeholder
            settingsForm.classList.add('hidden');
            settingsPlaceholder.classList.remove('hidden');
            
            // Clear server info display
            document.getElementById('current-host').textContent = '-';
            document.getElementById('current-port').textContent = '-';
        }
    }
    
    loadConsoleHistory(history) {
        const consoleOutput = document.getElementById('console-output');
        
        // Clear existing content except welcome message
        const welcomeMessage = consoleOutput.querySelector('.text-green-400.font-bold');
        consoleOutput.innerHTML = '';
        if (welcomeMessage) {
            consoleOutput.appendChild(welcomeMessage);
        }
        
        // Add history
        history.forEach(entry => {
            this.addConsoleMessage(entry.timestamp, entry.message, entry.type, false);
        });
        
        this.scrollToBottom();
    }
    
    addConsoleMessage(timestamp, message, type = 'server', scroll = true) {
        const consoleOutput = document.getElementById('console-output');
        const line = document.createElement('div');
        
        // Set Tailwind classes based on message type
        let lineClasses = 'mb-1 break-words whitespace-pre-wrap';
        switch(type) {
            case 'welcome':
                lineClasses += ' text-green-400 font-bold';
                break;
            case 'command':
                lineClasses += ' text-yellow-300';
                break;
            case 'error':
                lineClasses += ' text-red-500';
                break;
            case 'server':
            default:
                lineClasses += ' text-github-text';
                break;
        }
        line.className = lineClasses;
        
        const timestampSpan = document.createElement('span');
        timestampSpan.className = 'text-github-text-muted text-xs mr-2';
        timestampSpan.textContent = this.formatTimestamp(timestamp);
        
        const messageSpan = document.createElement('span');
        messageSpan.className = 'text-inherit';
        messageSpan.textContent = message;
        
        line.appendChild(timestampSpan);
        line.appendChild(messageSpan);
        
        consoleOutput.appendChild(line);
        
        // Limit console lines
        const maxLines = 1000;
        const lines = consoleOutput.querySelectorAll('div:not(.text-green-400.font-bold)');
        if (lines.length > maxLines) {
            lines[0].remove();
        }
        
        if (scroll && this.autoScroll) {
            this.scrollToBottom();
        }
    }
    
    formatTimestamp(timestamp) {
        if (timestamp === 'SYSTEM' || timestamp === 'ERROR') {
            return `[${timestamp}]`;
        }
        
        try {
            // If timestamp is already in HH:MM:SS format, use it directly
            if (typeof timestamp === 'string' && timestamp.match(/^\d{1,2}:\d{2}:\d{2}$/)) {
                return `[${timestamp}]`;
            }
            
            // If timestamp is already in [HH:MM:SS] format, return as is
            if (typeof timestamp === 'string' && timestamp.match(/^\[\d{1,2}:\d{2}:\d{2}\]$/)) {
                return timestamp;
            }
            
            // Try to parse as ISO date string or create new date
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) {
                // If parsing failed, try to extract time from current time
                const now = new Date();
                return `[${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}]`;
            }
            
            return `[${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}:${date.getSeconds().toString().padStart(2, '0')}]`;
        } catch (error) {
            // Fallback to current time
            const now = new Date();
            return `[${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}]`;
        }
    }
    
    sendCommand() {
        const commandInput = document.getElementById('command-input');
        const command = commandInput.value.trim();
        
        if (!command) return;
        
        if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
            this.addConsoleMessage('ERROR', 'Not connected to PocketMine server', 'error');
            return;
        }
        
        try {
            // Send command to PocketMine server
            const commandMessage = JSON.stringify({
                type: 'command',
                command: command,
                password: this.config.pocketminePassword || ''
            });
            
            this.socket.send(commandMessage);
            
            // Log the command in console history
            const logEntry = {
                timestamp: this.getCurrentTimestamp(),
                message: `> ${command}`,
                type: 'command'
            };
            
            this.consoleHistory.push(logEntry);
            if (this.consoleHistory.length > this.config.maxConsoleLines) {
                this.consoleHistory = this.consoleHistory.slice(-this.config.maxConsoleLines);
            }
            
            // Show command in console
            this.addConsoleMessage(logEntry.timestamp, logEntry.message, logEntry.type);
            
        } catch (error) {
            console.error('Error sending command to PocketMine:', error);
            this.addConsoleMessage('ERROR', `Failed to send command: ${error.message}`, 'error');
        }
        
        // Add to command history
        this.commandHistory.unshift(command);
        if (this.commandHistory.length > 50) {
            this.commandHistory.pop();
        }
        this.historyIndex = -1;
        
        // Clear input
        commandInput.value = '';
    }
    
    navigateHistory(direction) {
        const commandInput = document.getElementById('command-input');
        
        if (this.commandHistory.length === 0) return;
        
        this.historyIndex += direction;
        
        if (this.historyIndex < -1) {
            this.historyIndex = -1;
            commandInput.value = '';
        } else if (this.historyIndex >= this.commandHistory.length) {
            this.historyIndex = this.commandHistory.length - 1;
        }
        
        if (this.historyIndex >= 0) {
            commandInput.value = this.commandHistory[this.historyIndex];
        }
    }
    
    clearConsole() {
        const consoleOutput = document.getElementById('console-output');
        const welcomeMessage = consoleOutput.querySelector('.text-green-400.font-bold');
        consoleOutput.innerHTML = '';
        
        if (welcomeMessage) {
            consoleOutput.appendChild(welcomeMessage);
        }
        
        this.addConsoleMessage('SYSTEM', 'Console cleared', 'welcome');
    }
    
    toggleAutoScroll() {
        this.autoScroll = !this.autoScroll;
        const button = document.getElementById('auto-scroll-toggle');
        
        if (this.autoScroll) {
            button.className = 'bg-green-500 text-white px-3 py-2 rounded text-sm font-mono transition-colors hover:bg-green-600';
            button.textContent = 'Auto Scroll';
            this.scrollToBottom();
        } else {
            button.className = 'bg-github-border text-github-text px-3 py-2 rounded text-sm font-mono transition-colors hover:bg-gray-600';
            button.textContent = 'Manual Scroll';
        }
    }
    
    scrollToBottom() {
        const consoleOutput = document.getElementById('console-output');
        consoleOutput.scrollTop = consoleOutput.scrollHeight;
    }
    
    exportLogs() {
        const consoleLines = document.querySelectorAll('#console-output div:not(.text-green-400.font-bold)');
        let logs = '';
        
        consoleLines.forEach(line => {
            const timestamp = line.querySelector('.text-github-text-muted').textContent;
            const message = line.querySelector('.text-inherit').textContent;
            logs += `${timestamp} ${message}\n`;
        });
        
        const blob = new Blob([logs], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `pocketmine-console-${new Date().toISOString().split('T')[0]}.log`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.addConsoleMessage('SYSTEM', 'Console logs exported', 'welcome');
    }
    
    updateServerConfig() {
        const host = document.getElementById('host-input').value.trim();
        const port = parseInt(document.getElementById('port-input').value);
        const password = document.getElementById('password-input').value; // Don't trim password as spaces might be intentional
        
        if (!host || !port || port < 1 || port > 65535) {
            this.addConsoleMessage('ERROR', 'Invalid host or port configuration', 'error');
            return;
        }
        
        // Update configuration
        this.config.pocketmineHost = host;
        this.config.pocketminePort = port;
        this.config.pocketminePassword = password;
        
        // Save to localStorage
        this.saveConfiguration();
        
        // Update UI
        document.getElementById('current-host').textContent = host;
        document.getElementById('current-port').textContent = port;
        
        // Close existing connection if any
        if (this.socket) {
            this.socket.close();
        }
        
        if (this.reconnectInterval) {
            clearInterval(this.reconnectInterval);
            this.reconnectInterval = null;
        }
        
        // Only update configuration, don't auto-connect
        // User must explicitly click "Connect" to establish connection
        this.addConsoleMessage('SYSTEM', 'Server configuration updated. Click "Connect" to establish connection.', 'welcome');
    }
    
}

// Initialize the console when the page loads
document.addEventListener('DOMContentLoaded', () => {
    window.console = new PocketMineConsole();
});

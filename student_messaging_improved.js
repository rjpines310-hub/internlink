// Improved messaging functionality for student dashboard
let currentConversation = null;
let messageRefreshInterval = null;
let conversationRefreshInterval = null;
let eventSource = null;

// Initialize messaging when tab is shown
function initializeMessaging() {
  loadConversations();
  startRealtimeUpdates();
  setupEventListeners();
}

// Setup event listeners
function setupEventListeners() {
  const sendBtn = document.getElementById('sendBtn');
  const messageInput = document.getElementById('messageText');
  const newChatBtn = document.getElementById('newChatBtn');
  const searchInput = document.getElementById('userSearchInput');

  if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
  }

  if (messageInput) {
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  if (newChatBtn) {
    newChatBtn.addEventListener('click', showNewChatModal);
  }

  if (searchInput) {
    searchInput.addEventListener('input', debounce(searchUsers, 300));
  }
}

// Load conversations with unread count
function loadConversations() {
  fetch('fetch_messages_improved.php?action=conversations')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displayConversations(data.conversations);
      } else {
        showError('Error loading conversations');
      }
    })
    .catch(() => showError('Error loading conversations'));
}

// Display conversations with unread indicators
function displayConversations(conversations) {
  const container = document.getElementById('conversations');
  container.innerHTML = '';

  if (conversations.length === 0) {
    container.innerHTML = '<p class="no-conversations">No conversations yet. <button onclick="showNewChatModal()" class="start-chat-btn">Start a chat</button></p>';
    return;
  }

  conversations.forEach(conv => {
    const div = document.createElement('div');
    div.className = 'conversation-item';
    if (conv.unread_count > 0) {
      div.classList.add('unread');
    }
    
    const timeAgo = getTimeAgo(conv.last_time);
    const unreadBadge = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
    
    div.innerHTML = `
      <div class="conversation-header">
        <strong class="contact-name">${escapeHtml(conv.name)}</strong>
        <span class="conversation-time">${timeAgo}</span>
        ${unreadBadge}
      </div>
      <div class="last-message">${escapeHtml(conv.last_message || 'No messages')}</div>
    `;
    
    div.onclick = () => selectConversation(conv.other_type, conv.other_id, conv.name);
    container.appendChild(div);
  });
}

// Select conversation and load messages
function selectConversation(other_type, other_id, name) {
  currentConversation = { other_type, other_id, name };
  document.getElementById('chat-title').textContent = `Chat with ${name}`;
  
  // Update UI to show selected conversation
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.classList.remove('active');
  });
  event.currentTarget.classList.add('active');
  
  loadMessages();
  showChatArea();
}

// Load messages for current conversation
function loadMessages() {
  if (!currentConversation) return;
  
  const { other_type, other_id } = currentConversation;
  fetch(`fetch_messages_improved.php?action=messages&other_type=${other_type}&other_id=${other_id}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displayMessages(data.messages);
        // Refresh conversations to update unread count
        loadConversations();
      } else {
        showError('Error loading messages');
      }
    })
    .catch(() => showError('Error loading messages'));
}

// Display messages with better formatting
function displayMessages(messages) {
  const container = document.getElementById('messages');
  container.innerHTML = '';

  if (messages.length === 0) {
    container.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
    return;
  }

  messages.forEach(msg => {
    const div = document.createElement('div');
    const isSent = msg.sender_type === 'student';
    div.className = `message ${isSent ? 'sent' : 'received'}`;
    div.setAttribute('data-message-id', msg.id);

    const time = new Date(msg.sent_at).toLocaleString();
    const readStatus = isSent ? (msg.is_read ? '✓✓' : '✓') : '';

    div.innerHTML = `
      <div class="message-content">${escapeHtml(msg.message)}</div>
      <div class="message-meta">
        <span class="message-time">${time}</span>
        <span class="read-status">${readStatus}</span>
      </div>
    `;

    container.appendChild(div);
  });

  // Scroll to bottom
  container.scrollTop = container.scrollHeight;
}

// Send message with better error handling
function sendMessage() {
  const input = document.getElementById('messageText');
  const message = input.value.trim();

  if (!message || !currentConversation) {
    return;
  }

  if (message.length > 500) {
    showError('Message too long. Maximum 500 characters.');
    return;
  }

  const { other_type, other_id } = currentConversation;
  const formData = new FormData();
  formData.append('other_type', other_type);
  formData.append('other_id', other_id);
  formData.append('message', message);

  // Disable send button temporarily
  const sendBtn = document.getElementById('sendBtn');
  sendBtn.disabled = true;
  sendBtn.textContent = 'Sending...';

  fetch('send_message.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        input.value = '';
        // Append the sent message locally for immediate feedback
        appendMessageLocally(message);
      } else {
        showError('Error: ' + data.message);
      }
    })
    .catch(() => showError('Error sending message'))
    .finally(() => {
      sendBtn.disabled = false;
      sendBtn.textContent = 'Send';
    });
}

// Append sent message locally to UI
function appendMessageLocally(message) {
  const container = document.getElementById('messages');
  const div = document.createElement('div');
  div.className = 'message sent';
  // Note: We don't have the ID yet, but since it's sent, SSE won't duplicate it as it's not in the DB yet
  // When SSE sends it back, it will have ID and be filtered

  const time = new Date().toLocaleString();
  const readStatus = '✓'; // Initially not read by receiver

  div.innerHTML = `
    <div class="message-content">${escapeHtml(message)}</div>
    <div class="message-meta">
      <span class="message-time">${time}</span>
      <span class="read-status">${readStatus}</span>
    </div>
  `;

  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

// Show new chat modal
function showNewChatModal() {
  const modal = document.getElementById('newChatModal');
  if (modal) {
    modal.style.display = 'block';
    document.getElementById('userSearchInput').focus();
  }
}

// Hide new chat modal
function hideNewChatModal() {
  const modal = document.getElementById('newChatModal');
  if (modal) {
    modal.style.display = 'none';
    document.getElementById('userSearchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
  }
}

// Search users for new conversation
function searchUsers() {
  const query = document.getElementById('userSearchInput').value.trim();
  if (query.length < 2) {
    document.getElementById('searchResults').innerHTML = '';
    return;
  }
  
  fetch(`fetch_messages_improved.php?action=search_users&query=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displaySearchResults(data.users);
      }
    })
    .catch(() => showError('Error searching users'));
}

// Display search results
function displaySearchResults(users) {
  const container = document.getElementById('searchResults');
  container.innerHTML = '';
  
  if (users.length === 0) {
    container.innerHTML = '<div class="no-results">No users found</div>';
    return;
  }
  
  users.forEach(user => {
    const div = document.createElement('div');
    div.className = 'search-result-item';
    div.innerHTML = `
      <span class="user-name">${escapeHtml(user.name)}</span>
      <span class="user-type">${user.type}</span>
    `;
    div.onclick = () => startNewConversation(user.type, user.id, user.name);
    container.appendChild(div);
  });
}

// Start new conversation
function startNewConversation(type, id, name) {
  hideNewChatModal();
  selectConversation(type, id, name);
}

// Realtime updates using SSE
function startRealtimeUpdates() {
  if (eventSource) {
    eventSource.close();
  }

  eventSource = new EventSource('fetch_messages_realtime.php');

  eventSource.addEventListener('new_messages', function(event) {
    const data = JSON.parse(event.data);
    handleNewMessages(data.messages);
  });

  eventSource.addEventListener('conversations_update', function(event) {
    const data = JSON.parse(event.data);
    displayConversations(data.conversations);
  });

  eventSource.onerror = function(event) {
    console.error('SSE error:', event);
    // Optionally retry or fallback to polling
  };
}

function stopRealtimeUpdates() {
  if (eventSource) {
    eventSource.close();
    eventSource = null;
  }
}

// Handle new messages from SSE
function handleNewMessages(newMessages) {
  if (!currentConversation) return;

  // Filter messages for current conversation
  const relevantMessages = newMessages.filter(msg => {
    return (msg.sender_type === currentConversation.other_type && msg.sender_id == currentConversation.other_id) ||
           (msg.receiver_type === currentConversation.other_type && msg.receiver_id == currentConversation.other_id);
  });

  if (relevantMessages.length > 0) {
    // Append new messages to current display, avoiding duplicates
    const container = document.getElementById('messages');
    relevantMessages.forEach(msg => {
      if (!document.querySelector(`[data-message-id="${msg.id}"]`)) {
        const div = document.createElement('div');
        const isSent = msg.sender_type === 'student';
        div.className = `message ${isSent ? 'sent' : 'received'}`;
        div.setAttribute('data-message-id', msg.id);

        const time = new Date(msg.sent_at).toLocaleString();
        const readStatus = isSent ? (msg.is_read ? '✓✓' : '✓') : '';

        div.innerHTML = `
          <div class="message-content">${escapeHtml(msg.message)}</div>
          <div class="message-meta">
            <span class="message-time">${time}</span>
            <span class="read-status">${readStatus}</span>
          </div>
        `;

        container.appendChild(div);
      }
    });

    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
  }
}

// Show chat area (for mobile responsiveness)
function showChatArea() {
  const chatArea = document.querySelector('.chat-area');
  const conversationsList = document.querySelector('.conversations-list');
  
  if (window.innerWidth <= 768) {
    conversationsList.style.display = 'none';
    chatArea.style.display = 'flex';
  }
}

// Show conversations list (for mobile)
function showConversationsList() {
  const chatArea = document.querySelector('.chat-area');
  const conversationsList = document.querySelector('.conversations-list');
  
  if (window.innerWidth <= 768) {
    conversationsList.style.display = 'block';
    chatArea.style.display = 'none';
  }
}

// Utility functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function getTimeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);
  
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function showError(message) {
  // You can replace this with a better notification system
  console.error(message);
  alert(message);
}

// Clean up when leaving messaging tab
function cleanupMessaging() {
  stopRealtimeUpdates();
  currentConversation = null;
}

import { useEffect, useState } from 'react';
import { api } from '../services/api';
import { Sidebar } from '../components/Layout/Sidebar';
import { MessageList } from '../components/Chat/MessageList';
import { MessageInput } from '../components/Chat/MessageInput';
import './DashboardPage.css';

export function DashboardPage({ user, onLogout }) {
  const [conversations, setConversations] = useState([]);
  const [selectedConv, setSelectedConv] = useState(null);
  const [messages, setMessages] = useState([]);
  const [loadingConvs, setLoadingConvs] = useState(true);
  const [loadingMessages, setLoadingMessages] = useState(false);

  const loadConversations = async () => {
    setLoadingConvs(true);
    try {
      const data = await api.getConversations();
      setConversations(data);
      if (!selectedConv && data.length > 0) {
        handleSelectConversation(data[0]);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingConvs(false);
    }
  };

  const handleSelectConversation = async (conv) => {
    setSelectedConv(conv);
    setLoadingMessages(true);
    try {
      const data = await api.getConversation(conv.id);
      setMessages(data.messages);
      // Mark as read (fire and forget)
      api.markConversationRead(conv.id).catch(() => {});
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingMessages(false);
    }
  };

  const handleSend = async (content) => {
    if (!selectedConv) return;
    try {
      const msg = await api.sendMessage(selectedConv.id, content);
      setMessages((prev) => [...prev, msg]);
      // Optimistically bump last message in sidebar
      setConversations((prev) =>
        prev.map((c) =>
          c.id === selectedConv.id ? { ...c, last_message: msg } : c
        )
      );
    } catch (err) {
      alert(err.message || 'Failed to send');
    }
  };

  useEffect(() => {
    loadConversations();
  }, []);

  return (
    <div className="dashboard-root">
      <header className="dashboard-header">
        <div className="dashboard-header-left">
          <h1>Sales Chat</h1>
          <span className="dashboard-tag">WhatsApp-style</span>
        </div>
        <div className="dashboard-header-right">
          <span className="dashboard-user">
            {user.name} <span className="dashboard-role">({user.role})</span>
          </span>
          <button
            onClick={() => {
              localStorage.removeItem('token');
              localStorage.removeItem('user');
              onLogout();
            }}
          >
            Logout
          </button>
        </div>
      </header>
      <div className="dashboard-body">
        <Sidebar
          conversations={conversations}
          selectedId={selectedConv?.id}
          onSelect={handleSelectConversation}
        />
        <main className="dashboard-main">
          {!selectedConv && (
            <div className="dashboard-empty">
              {loadingConvs ? 'Loading conversations…' : 'Select a conversation'}
            </div>
          )}
          {selectedConv && (
            <div className="dashboard-chat">
              <div className="dashboard-chat-header">
                <div>
                  <h2>{selectedConv.name}</h2>
                  <span className="dashboard-chat-phone">
                    {selectedConv.phone}
                  </span>
                </div>
              </div>
              <MessageList
                messages={messages}
                currentUserId={user.id}
              />
              <MessageInput
                onSend={handleSend}
                disabled={loadingMessages}
              />
            </div>
          )}
        </main>
      </div>
    </div>
  );
}


import './Sidebar.css';

/**
 * Sidebar listing customer conversations.
 */
export function Sidebar({ conversations, selectedId, onSelect }) {
  return (
    <aside className="sidebar">
      <header className="sidebar-header">
        <h2>Conversations</h2>
      </header>
      <div className="sidebar-list">
        {conversations.length === 0 && (
          <div className="sidebar-empty">No conversations yet.</div>
        )}
        {conversations.map((conv) => (
          <button
            key={conv.id}
            className={
              'sidebar-item' +
              (conv.id === selectedId ? ' sidebar-item--active' : '')
            }
            onClick={() => onSelect(conv)}
          >
            <div className="sidebar-item-main">
              <span className="sidebar-item-name">{conv.name}</span>
              {conv.unread_count > 0 && (
                <span className="sidebar-item-unread">
                  {conv.unread_count}
                </span>
              )}
            </div>
            <div className="sidebar-item-sub">
              <span className="sidebar-item-phone">{conv.phone}</span>
              {conv.last_message && (
                <span className="sidebar-item-last">
                  {conv.last_message.content.slice(0, 30)}
                  {conv.last_message.content.length > 30 ? '…' : ''}
                </span>
              )}
            </div>
          </button>
        ))}
      </div>
    </aside>
  );
}


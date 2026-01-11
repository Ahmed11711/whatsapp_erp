import './MessageList.css';

function formatTime(value) {
  const date = new Date(value);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Renders the messages within a conversation.
 */
/**
 * Renders the messages within a conversation.
 * 
 * Handles both inbound (from customer) and outbound (from agent) messages.
 * Inbound messages have sender_id = null, outbound messages have sender_id = agent user id.
 */
export function MessageList({ messages, currentUserId }) {
  return (
    <div className="message-list">
      {messages.length === 0 && (
        <div className="message-list-empty">
          No messages yet. Start the conversation!
        </div>
      )}
      {messages.map((msg) => {
        // Message is "mine" (sent by agent) if sender_id matches current user
        // Inbound messages have sender_id = null, so they're always "theirs"
        const isMine = msg.sender_id === currentUserId;
        const isInbound = msg.direction === 'inbound' || msg.sender_id === null;
        
        return (
          <div
            key={msg.id}
            className={
              'message-row ' + (isMine ? 'message-row--mine' : 'message-row--theirs')
            }
          >
            <div className="message-bubble">
              <div className="message-content">{msg.content}</div>
              <div className="message-meta">
                <span className="message-sender">
                  {isMine ? 'You' : msg.sender?.name || 'Customer'}
                </span>
                <span className="message-time">
                  {msg.created_at ? formatTime(msg.created_at) : ''}
                </span>
                {isMine && (
                  <span className="message-status">
                    {msg.status === 'read' ? '✓✓ Read' : msg.status === 'delivered' ? '✓✓ Delivered' : '✓ Sent'}
                  </span>
                )}
                {isInbound && (
                  <span className="message-status message-status--inbound">
                    Received
                  </span>
                )}
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}


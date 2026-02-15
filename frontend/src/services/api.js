// ===============================
// API Client (Vite + Laravel)
// ===============================

// Base URL من env أو fallback
const API_URL =
  import.meta.env.VITE_API_URL || 'https://chat.mag-opt.com';
  

// ===============================
// Headers
// ===============================
function getAuthHeaders() {
  const token = localStorage.getItem('token');

  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

// ===============================
// Request Wrapper
// ===============================
async function request(path, options = {}) {
  const response = await fetch(`${API_URL}/api${path}`, {
    ...options,
    headers: {
      ...getAuthHeaders(),
      ...(options.headers || {}),
    },
  });

  // لو الرد مش OK
  if (!response.ok) {
    let errorMessage = 'Request failed';

    try {
      const errorData = await response.json();
      errorMessage = errorData.message || JSON.stringify(errorData);
    } catch (e) {
      errorMessage = response.statusText;
    }

    throw new Error(errorMessage);
  }

  // No Content
  if (response.status === 204) return null;

  return response.json();
}

// ===============================
// API Methods
// ===============================
export const api = {
  // ---------- Auth ----------
  login: (email, password) =>
    request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  me: () => request('/auth/me'),

  logout: () =>
    request('/auth/logout', {
      method: 'POST',
    }),

  // ---------- Conversations ----------
  getConversations: () => request('/conversations'),

  getConversation: (customerId) =>
    request(`/conversations/${customerId}`),

  sendMessage: (customerId, content) =>
    request(`/conversations/${customerId}/messages`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    }),

  markConversationRead: (customerId) =>
    request(`/customers/${customerId}/read`, {
      method: 'POST',
    }),
};

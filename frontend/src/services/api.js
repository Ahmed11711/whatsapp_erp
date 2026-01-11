// Simple API client wrapping fetch with the base URL from env variables.
// Vite exposes env vars prefixed with VITE_ via import.meta.env.

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

function getAuthHeaders() {
  const token = localStorage.getItem('token');
  return token
    ? { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }
    : { 'Content-Type': 'application/json' };
}

async function request(path, options = {}) {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      ...getAuthHeaders(),
      ...(options.headers || {}),
    },
  });

  if (!res.ok) {
    let message = 'Request failed';
    try {
      const data = await res.json();
      message = data.message || JSON.stringify(data);
    } catch (_) {}
    throw new Error(message);
  }

  if (res.status === 204) return null;
  return res.json();
}

export const api = {
  login: (email, password) =>
    request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  me: () => request('/auth/me'),

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


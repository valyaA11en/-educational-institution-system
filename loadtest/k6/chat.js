import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '2m', target: 15 }, // Ramp up to 15 users
    { duration: '5m', target: 15 }, // Stay at 15 users
    { duration: '2m', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<800'], // 95% of requests should be below 800ms
    errors: ['rate<0.1'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL || 'student@example.com';
const PASSWORD = __ENV.PASSWORD || 'password';

let token = null;
let threadId = null;

export function setup() {
  // Login
  const loginRes = http.post(`${BASE_URL}/api/v1/auth/login`, JSON.stringify({
    email: EMAIL,
    password: PASSWORD,
  }), {
    headers: { 'Content-Type': 'application/json' },
  });

  if (loginRes.status !== 200) {
    throw new Error('Failed to login in setup');
  }

  token = JSON.parse(loginRes.body).access_token;
  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };

  // Get threads
  const threadsRes = http.get(`${BASE_URL}/api/v1/chats/threads`, { headers });
  if (threadsRes.status === 200) {
    const threads = JSON.parse(threadsRes.body);
    if (threads.data && threads.data.length > 0) {
      threadId = threads.data[0].id;
    } else {
      // Create a thread if none exists
      const createRes = http.post(`${BASE_URL}/api/v1/chats/threads`, JSON.stringify({
        title: 'Load Test Thread',
        type: 'group',
      }), { headers });
      if (createRes.status === 200 || createRes.status === 201) {
        threadId = JSON.parse(createRes.body).id;
      }
    }
  }

  return { token, threadId };
}

export default function (data) {
  if (!data.token) {
    return;
  }

  const headers = {
    'Authorization': `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  // Get threads
  const threadsRes = http.get(`${BASE_URL}/api/v1/chats/threads`, { headers });
  const threadsSuccess = check(threadsRes, {
    'threads status is 200': (r) => r.status === 200,
  });
  errorRate.add(!threadsSuccess);

  sleep(1);

  // Get messages for a thread
  if (data.threadId) {
    const messagesRes = http.get(
      `${BASE_URL}/api/v1/chats/threads/${data.threadId}/messages`,
      { headers }
    );
    const messagesSuccess = check(messagesRes, {
      'messages status is 200': (r) => r.status === 200,
    });
    errorRate.add(!messagesSuccess);

    sleep(1);

    // Send a message
    const messagePayload = {
      content: `Load test message at ${new Date().toISOString()}`,
    };
    const sendRes = http.post(
      `${BASE_URL}/api/v1/chats/threads/${data.threadId}/messages`,
      JSON.stringify(messagePayload),
      { headers }
    );
    const sendSuccess = check(sendRes, {
      'send message status is 200 or 201': (r) => r.status === 200 || r.status === 201,
    });
    errorRate.add(!sendSuccess);

    sleep(2);
  }
}



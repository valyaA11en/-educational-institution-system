import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '1m', target: 10 }, // Ramp up to 10 users
    { duration: '2m', target: 10 }, // Stay at 10 users
    { duration: '1m', target: 0 },  // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
    errors: ['rate<0.1'], // Error rate should be less than 10%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL || 'admin@example.com';
const PASSWORD = __ENV.PASSWORD || 'password';

export default function () {
  // Login
  const loginRes = http.post(`${BASE_URL}/api/v1/auth/login`, JSON.stringify({
    email: EMAIL,
    password: PASSWORD,
  }), {
    headers: { 'Content-Type': 'application/json' },
  });

  const loginSuccess = check(loginRes, {
    'login status is 200': (r) => r.status === 200,
    'login has access_token': (r) => JSON.parse(r.body).access_token !== undefined,
  });

  errorRate.add(!loginSuccess);

  if (!loginSuccess) {
    return;
  }

  const token = JSON.parse(loginRes.body).access_token;
  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };

  // Get /api/auth/me (meta)
  const meRes = http.get(`${BASE_URL}/api/v1/auth/me`, { headers });
  const meSuccess = check(meRes, {
    'me status is 200': (r) => r.status === 200,
    'me has user data': (r) => JSON.parse(r.body).user !== undefined,
  });
  errorRate.add(!meSuccess);

  sleep(1);

  // Get notifications
  const notificationsRes = http.get(`${BASE_URL}/api/v1/notifications`, { headers });
  const notificationsSuccess = check(notificationsRes, {
    'notifications status is 200': (r) => r.status === 200,
  });
  errorRate.add(!notificationsSuccess);

  sleep(1);
}



import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '2m', target: 100 }, // Ramp up to 100 users
    { duration: '5m', target: 100 }, // Stay at 100 users
    { duration: '2m', target: 200 }, // Ramp up to 200 users
    { duration: '5m', target: 200 }, // Stay at 200 users
    { duration: '2m', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
    errors: ['rate<0.1'], // Error rate should be less than 10%
  },
};

const BASE_URL = __ENV.API_URL || 'http://localhost/api';

let authToken = '';

export function setup() {
  // Login and get token
  const loginRes = http.post(`${BASE_URL}/v1/auth/login`, JSON.stringify({
    email: __ENV.USER_EMAIL || 'test@example.com',
    password: __ENV.USER_PASSWORD || 'password',
  }), {
    headers: { 'Content-Type': 'application/json' },
  });

  if (loginRes.status === 200) {
    const body = JSON.parse(loginRes.body);
    authToken = body.access_token;
  }

  return { token: authToken };
}

export default function (data) {
  const headers = {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${data.token}`,
  };

  // Test schedule endpoint
  const scheduleRes = http.get(`${BASE_URL}/v1/schedule`, { headers });
  check(scheduleRes, {
    'schedule status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(1);

  // Test journal endpoint
  const journalRes = http.get(`${BASE_URL}/v1/journal`, { headers });
  check(journalRes, {
    'journal status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(1);

  // Test exams endpoint
  const examsRes = http.get(`${BASE_URL}/v1/exams`, { headers });
  check(examsRes, {
    'exams status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(1);

  // Test contests endpoint
  const contestsRes = http.get(`${BASE_URL}/v1/contests`, { headers });
  check(contestsRes, {
    'contests status is 200': (r) => r.status === 200,
  }) || errorRate.add(1);

  sleep(1);
}

export function teardown(data) {
  // Cleanup if needed
}


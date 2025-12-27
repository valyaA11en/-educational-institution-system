import http from 'k6/http';
import { check } from 'k6';

export const options = {
  vus: 50,
  duration: '5m',
  thresholds: {
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
  },
};

const BASE_URL = __ENV.API_URL || 'http://localhost/api';

let authToken = '';

export function setup() {
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

  // Test complex queries that might be slow
  const res1 = http.get(`${BASE_URL}/v1/schedule?termId=1&groupId=1`, { headers });
  check(res1, {
    'schedule query < 1s': (r) => r.timings.duration < 1000,
  });

  const res2 = http.get(`${BASE_URL}/v1/journal?subjectId=1&groupId=1&termId=1`, { headers });
  check(res2, {
    'journal query < 1s': (r) => r.timings.duration < 1000,
  });

  const res3 = http.get(`${BASE_URL}/v1/exams?termId=1&groupId=1`, { headers });
  check(res3, {
    'exams query < 1s': (r) => r.timings.duration < 1000,
  });
}








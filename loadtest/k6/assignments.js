import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '2m', target: 10 }, // Ramp up to 10 users
    { duration: '5m', target: 10 }, // Stay at 10 users
    { duration: '2m', target: 0 },  // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    errors: ['rate<0.1'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL || 'student@example.com';
const PASSWORD = __ENV.PASSWORD || 'password';

let token = null;
let assignmentId = null;

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

  // Get assignments
  const assignmentsRes = http.get(`${BASE_URL}/api/v1/assignments`, { headers });
  if (assignmentsRes.status === 200) {
    const assignments = JSON.parse(assignmentsRes.body);
    if (assignments.data && assignments.data.length > 0) {
      assignmentId = assignments.data[0].id;
    }
  }

  return { token, assignmentId };
}

export default function (data) {
  if (!data.token) {
    return;
  }

  const headers = {
    'Authorization': `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  // Get assignments
  const assignmentsRes = http.get(`${BASE_URL}/api/v1/assignments`, { headers });
  const assignmentsSuccess = check(assignmentsRes, {
    'assignments status is 200': (r) => r.status === 200,
  });
  errorRate.add(!assignmentsSuccess);

  sleep(1);

  // Get presign URL for file upload (if assignment exists)
  if (data.assignmentId) {
    const presignRes = http.post(
      `${BASE_URL}/api/v1/files/presign`,
      JSON.stringify({
        filename: `test_${Date.now()}.txt`,
        content_type: 'text/plain',
      }),
      { headers }
    );
    const presignSuccess = check(presignRes, {
      'presign status is 200': (r) => r.status === 200,
      'presign has url': (r) => {
        const body = JSON.parse(r.body);
        return body.url !== undefined || body.upload_url !== undefined;
      },
    });
    errorRate.add(!presignSuccess);

    sleep(1);

    // TODO: Submit assignment (requires actual file upload to S3)
    // For now, just check that the endpoint exists
    const submitRes = http.post(
      `${BASE_URL}/api/v1/assignments/${data.assignmentId}/submit`,
      JSON.stringify({
        comment: 'Load test submission',
        // file_ids: [] // Would need actual file IDs
      }),
      { headers }
    );
    // Accept both success and validation errors
    check(submitRes, {
      'submit status is 200 or 400': (r) => r.status === 200 || r.status === 400,
    });

    sleep(2);
  }
}



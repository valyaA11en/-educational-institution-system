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
    http_req_duration: ['p(95)<1500'], // Documents can be slower
    errors: ['rate<0.1'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL || 'admin@example.com';
const PASSWORD = __ENV.PASSWORD || 'password';

let token = null;
let documentId = null;

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

  // Get documents list
  const documentsRes = http.get(`${BASE_URL}/api/v1/documents`, { headers });
  if (documentsRes.status === 200) {
    const documents = JSON.parse(documentsRes.body);
    if (documents.data && documents.data.length > 0) {
      documentId = documents.data[0].id;
    }
  }

  return { token, documentId };
}

export default function (data) {
  if (!data.token) {
    return;
  }

  const headers = {
    'Authorization': `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  // List documents
  const documentsRes = http.get(`${BASE_URL}/api/v1/documents`, { headers });
  const documentsSuccess = check(documentsRes, {
    'documents status is 200': (r) => r.status === 200,
  });
  errorRate.add(!documentsSuccess);

  sleep(1);

  // Export document as DOCX (if document exists and export is available)
  if (data.documentId) {
    const exportRes = http.get(
      `${BASE_URL}/api/v1/documents/${data.documentId}/export`,
      {
        headers,
        params: { format: 'docx' },
      }
    );
    // Accept both success and "not available" errors
    const exportSuccess = check(exportRes, {
      'export status is 200 or 404 or 400': (r) => 
        r.status === 200 || r.status === 404 || r.status === 400,
    });
    // Don't count 404/400 as errors for this test (export might not be available)
    if (exportRes.status !== 200 && exportRes.status !== 404 && exportRes.status !== 400) {
      errorRate.add(true);
    }

    sleep(2);
  }
}



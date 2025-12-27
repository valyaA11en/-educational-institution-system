import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errorRate = new Rate('errors');

export const options = {
  stages: [
    { duration: '2m', target: 20 }, // Ramp up to 20 users
    { duration: '5m', target: 20 }, // Stay at 20 users
    { duration: '2m', target: 0 },  // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'], // 95% of requests should be below 1s
    errors: ['rate<0.1'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const EMAIL = __ENV.EMAIL || 'scheduler@example.com';
const PASSWORD = __ENV.PASSWORD || 'password';

let token = null;
let versionId = null;
let groupId = null;
let subjectId = null;
let teacherId = null;
let roomId = null;
let timeSlotId = null;

export function setup() {
  // Login and get initial data
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

  // Get schedule versions
  const versionsRes = http.get(`${BASE_URL}/api/v1/schedule/versions`, { headers });
  if (versionsRes.status === 200) {
    const versions = JSON.parse(versionsRes.body);
    if (versions.data && versions.data.length > 0) {
      versionId = versions.data[0].id;
    }
  }

  // Get directory data
  const groupsRes = http.get(`${BASE_URL}/api/v1/directory/groups`, { headers });
  if (groupsRes.status === 200) {
    const groups = JSON.parse(groupsRes.body);
    if (groups.length > 0) {
      groupId = groups[0].id;
    }
  }

  const subjectsRes = http.get(`${BASE_URL}/api/v1/directory/subjects`, { headers });
  if (subjectsRes.status === 200) {
    const subjects = JSON.parse(subjectsRes.body);
    if (subjects.length > 0) {
      subjectId = subjects[0].id;
    }
  }

  const timeSlotsRes = http.get(`${BASE_URL}/api/v1/directory/time-slots`, { headers });
  if (timeSlotsRes.status === 200) {
    const timeSlots = JSON.parse(timeSlotsRes.body);
    if (timeSlots.length > 0) {
      timeSlotId = timeSlots[0].id;
    }
  }

  // Get user data for teacher
  const meRes = http.get(`${BASE_URL}/api/v1/auth/me`, { headers });
  if (meRes.status === 200) {
    const me = JSON.parse(meRes.body);
    teacherId = me.user?.id;
  }

  const roomsRes = http.get(`${BASE_URL}/api/v1/directory/rooms`, { headers });
  if (roomsRes.status === 200) {
    const rooms = JSON.parse(roomsRes.body);
    if (rooms.length > 0) {
      roomId = rooms[0].id;
    }
  }

  return {
    token,
    versionId,
    groupId,
    subjectId,
    teacherId,
    roomId,
    timeSlotId,
  };
}

export default function (data) {
  if (!data.token) {
    return;
  }

  const headers = {
    'Authorization': `Bearer ${data.token}`,
    'Content-Type': 'application/json',
  };

  // Read schedule items
  const itemsRes = http.get(`${BASE_URL}/api/v1/schedule/items`, {
    headers,
    params: { version_id: data.versionId || '' },
  });
  const itemsSuccess = check(itemsRes, {
    'schedule items status is 200': (r) => r.status === 200,
  });
  errorRate.add(!itemsSuccess);

  sleep(1);

  // Create schedule item (if we have all required data)
  if (data.versionId && data.groupId && data.subjectId && data.teacherId && data.roomId && data.timeSlotId) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];

    const createPayload = {
      schedule_version_id: data.versionId,
      date: dateStr,
      time_slot_id: data.timeSlotId,
      group_id: data.groupId,
      subject_id: data.subjectId,
      teacher_user_id: data.teacherId,
      room_id: data.roomId,
    };

    const createRes = http.post(
      `${BASE_URL}/api/v1/schedule/items`,
      JSON.stringify(createPayload),
      { headers }
    );

    const createSuccess = check(createRes, {
      'create item status is 201 or 200': (r) => r.status === 201 || r.status === 200,
    });
    errorRate.add(!createSuccess);

    // Check for conflicts
    if (createRes.status === 200 || createRes.status === 201) {
      const response = JSON.parse(createRes.body);
      if (response.conflicts && response.conflicts.length > 0) {
        // Force save with conflicts
        const forcePayload = {
          ...createPayload,
          force: true,
        };
        const forceRes = http.post(
          `${BASE_URL}/api/v1/schedule/items`,
          JSON.stringify(forcePayload),
          { headers }
        );
        check(forceRes, {
          'force save status is 201 or 200': (r) => r.status === 201 || r.status === 200,
        });
      }
    }

    sleep(1);
  }
}



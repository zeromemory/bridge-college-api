// Quick E2E test: simulates browser flow (register → login → get user → logout)
import http from 'node:http';

const BASE = 'http://localhost:8001';
let cookies = {};

function parseCookies(headers) {
  const setCookies = headers['set-cookie'] || [];
  for (const c of setCookies) {
    const [pair] = c.split(';');
    const [name, ...rest] = pair.split('=');
    cookies[name.trim()] = rest.join('=');
  }
}

function cookieHeader() {
  return Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; ');
}

function request(method, path, body) {
  return new Promise((resolve, reject) => {
    const url = new URL(path, BASE);
    const headers = {
      'Accept': 'application/json',
      'Origin': 'http://localhost:3000',
      'Referer': 'http://localhost:3000/',
      'Cookie': cookieHeader(),
    };
    if (body) {
      headers['Content-Type'] = 'application/json';
    }
    // Add XSRF token from cookie
    if (cookies['XSRF-TOKEN']) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies['XSRF-TOKEN']);
    }

    const req = http.request(url, { method, headers }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        parseCookies(res.headers);
        try {
          resolve({ status: res.statusCode, body: data ? JSON.parse(data) : null });
        } catch {
          resolve({ status: res.statusCode, body: data });
        }
      });
    });
    req.on('error', reject);
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

async function run() {
  const ts = Date.now();
  const cnicNum = String(ts).slice(-13).padStart(13, '3');
  const cnic = cnicNum.slice(0,5) + '-' + cnicNum.slice(5,12) + '-' + cnicNum.slice(12,13);
  let pass = 0, fail = 0;

  function check(name, ok) {
    if (ok) { console.log(`  ✓ ${name}`); pass++; }
    else { console.log(`  ✗ ${name}`); fail++; }
  }

  console.log('\n=== E2E Auth Flow Test ===\n');

  // 1. CSRF
  console.log('1. Get CSRF Cookie');
  const csrf = await request('GET', '/sanctum/csrf-cookie');
  check('Status 204', csrf.status === 204);
  check('XSRF-TOKEN cookie set', !!cookies['XSRF-TOKEN']);

  // 2. Register
  console.log('\n2. Register');
  const reg = await request('POST', '/api/v1/register', {
    name: 'E2E Test User',
    email: `e2e${ts}@test.com`,
    cnic: cnic,
    password: 'Password123!',
    password_confirmation: 'Password123!',
  });
  check('Status 201', reg.status === 201);
  check('success: true', reg.body?.success === true);
  check('User has id', !!reg.body?.data?.user?.id);
  check('Role is student', reg.body?.data?.user?.role === 'student');

  // 3. Refresh CSRF + Login
  console.log('\n3. Login');
  await request('GET', '/sanctum/csrf-cookie');
  const login = await request('POST', '/api/v1/login', { cnic, password: 'Password123!' });
  check('Status 200', login.status === 200);
  check('success: true', login.body?.success === true);
  check('User CNIC matches', login.body?.data?.user?.cnic === cnic);

  // 4. Get User (authenticated)
  console.log('\n4. Get User (authenticated)');
  const user = await request('GET', '/api/v1/user');
  check('Status 200', user.status === 200);
  check('Has user data', !!user.body?.data?.user?.id);
  check('Name matches', user.body?.data?.user?.name === 'E2E Test User');

  // 5. Logout
  console.log('\n5. Logout');
  const logout = await request('POST', '/api/v1/logout');
  check('Status 200', logout.status === 200);
  check('success: true', logout.body?.success === true);

  // 6. Get User after logout (should fail)
  console.log('\n6. Get User after logout');
  const noUser = await request('GET', '/api/v1/user');
  check('Status 401', noUser.status === 401);

  console.log(`\n=== Results: ${pass} passed, ${fail} failed ===\n`);
  process.exit(fail > 0 ? 1 : 0);
}

run().catch(e => { console.error(e); process.exit(1); });

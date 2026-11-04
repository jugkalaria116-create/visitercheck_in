# Testing Documentation

## 1. Testing Objective

The purpose of testing is to verify visitor registration, API functionality, administrative authentication, TV synchronization and security-related behaviour.

## 2. Testing Approach

Testing includes:
- Backend API testing.
- Frontend manual testing.
- Authentication testing.
- Database integration testing.
- TV display synchronization testing.
- Input validation testing.

## 3. Manual Test Cases

| ID | Test Scenario | Expected Result | Actual Result |
|---|---|---|---|
| TC01 | Open frontend application | Homepage loads | Not Run |
| TC02 | Open backend config API | Valid response is returned | Not Run |
| TC03 | Register a visitor with valid details | Visitor registration succeeds | Not Run |
| TC04 | Submit registration with missing required fields | Validation error is returned | Not Run |
| TC05 | Submit an invalid phone number | Invalid input is rejected | Not Run |
| TC06 | Upload an unsupported image | Upload is rejected | Not Run |
| TC07 | Open admin dashboard without authentication | Access is restricted | Not Run |
| TC08 | Log in with valid administrator credentials | Login succeeds | Not Run |
| TC09 | Register a new visitor while TV is open | TV displays updated visitor information | Not Run |
| TC10 | Keep TV display open without new registration | Existing display remains unchanged | Not Run |
| TC11 | Test visitor expiry | Expired visitor is no longer displayed | Not Run |
| TC12 | Disconnect and reconnect TV display | Expired visitor does not reappear | Not Run |

## 4. Automated Tests

The repository includes backend testing scripts under:

backend/scripts/

Review the scripts before executing them.

Potential commands, subject to their actual implementation:

```powershell
php backend/scripts/test-display-engine.php
php backend/scripts/smoke-test.php http://localhost:8000
```

Run commands from the project root only if those files exist and their usage matches these examples.

## 5. Environment Setup

1. Start MongoDB.
2. Configure backend/.env.
3. Install backend dependencies using composer install.
4. Start the PHP backend.
5. Install frontend dependencies using npm install.
6. Start the Vite frontend.

## 6. Verified Development Setup

| Check | Result |
|---|---|
| PHP CLI available | Verified |
| PHP OpenSSL extension | Verified |
| PHP MongoDB extension | Verified |
| Composer dependency installation | Passed |
| Frontend browser acceptance tests | Not Run |
| Visitor registration acceptance tests | Not Run |
| TV synchronization acceptance tests | Not Run |

## 7. Recording Test Results

After executing each test:
- Change Not Run to Passed or Failed.
- Record the actual result.
- Record the date of testing.
- Include error details for failed tests.
- Do not mark a test as passed without executing it.

# Project Assumptions

This document records assumptions made during development and why they were selected.

## 1. Visitor Registration
- Visitors provide their name, phone number and photo during registration.
- QR codes are used to access the visitor registration workflow.
- The registration interface should be simple enough for visitors without technical knowledge.

Reason: A QR-based workflow reduces manual registration effort.

## 2. Visitor Display
- The TV display shows the currently active visitor.
- A newly registered visitor replaces the previous visitor on the display.
- TV screens poll the backend approximately every 2 seconds.
- The backend is the source of truth for the current display state.

Reason: Polling provides a simple way to synchronize multiple TV displays without requiring WebSockets.

## 3. Visitor Expiry
- Visitor display information has a configurable retention period.
- Expiry is handled by the server.
- An expired visitor should not reappear after a TV reconnects.

Reason: Server-side expiry keeps display behaviour consistent.

## 4. Database
- MongoDB is used as the application's database.
- Visitor information and display state are stored in MongoDB.

Reason: MongoDB provides flexible document storage for visitor records.

## 5. Authentication
- Administrative functions require authentication.
- Public visitor registration and administrative operations have different access requirements.

Reason: Visitors should not be able to access administrative functionality.

## 6. Application Configuration
- Environment-specific settings are stored in environment variables.
- Development and production environments may use different URLs and database connections.

Reason: This avoids hardcoding environment-specific configuration.

## 7. Deployment
- The frontend and backend may be deployed separately.
- The frontend communicates with the backend through HTTP API endpoints.

Reason: Separate deployment allows independent frontend and backend management.

## 8. Limitations
- Polling may produce a small delay between registration and TV updates.
- The application requires an active backend and database connection.
- Actual visitor retention and upload limits depend on environment configuration.

## Updating assumptions
Any change to these assumptions should be documented when the corresponding feature or requirement changes.

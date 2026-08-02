# Change Log

## 6.1.1 - 2026-08-02

- Return HTTP 429 on CakePHP 5.0–5.2, which do not provide CakePHP's later `TooManyRequestsException` class.

## 6.1.0 - 2026-08-02

- Make the CakePHP plugin the canonical implementation and remove the runtime dependency on BruteForceShield.
- Store all challenge values as server-keyed HMACs and never log submitted values.
- Serialize limiter updates with inter-process locks and fail closed on storage errors.
- Enable a cross-IP limit by default to backstop spoofed or rotating client addresses.

## 0.1.0

- Initial CakePHP brute-force protection component.

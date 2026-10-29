# Changelog

## [Unreleased]

### Adăugat
- Gateway-ul `payu` cu două drivere: `payu-ro` (API v4, semnătură HMAC-SHA256 pe header-e) și `payu-gpo` (OpenPayU v2.1, OAuth2).
- Notificări verificate: HMAC v4 pe corpul brut, respectiv `OpenPayu-Signature` MD5 / SHA-256.
- Status, refund, captură și anulare pe ambele platforme.

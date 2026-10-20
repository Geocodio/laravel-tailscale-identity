# Changelog

All notable changes to `laravel-tailscale-identity` will be documented in this file.

## Unreleased

- Added `TailscaleIdentity::$profilePictureUrl` (nullable, last constructor parameter), parsed from `UserProfile.ProfilePicURL`; non-https, empty, or missing values are `null`.
- Added `fake.profile_picture_url` config (`TAILSCALE_IDENTITY_FAKE_PICTURE_URL`) and a `profilePictureUrl` argument on `FakeIdentityDriver`.
- Bumped the whois cache key prefix to `tailscale-identity:v2:`.

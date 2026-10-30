# Kinescope source for Moodle Video Bridge

This is the videoprogresssource_kinescope subplugin for local_video_bridge, based on the URL recognition approach of yasha4u/moodle-media_kinescope and extended with the official Kinescope IFrame Player API.

## Installation

Install the Video Bridge parent plugin first, with local_video_bridge version 2026102600 or newer. Place this repository's contents in local/video_bridge/source/kinescope and run Moodle's plugin installation or upgrade process. Moodle 4.5 or newer is required.

Select Kinescope as the video source in a Video Bridge-compatible activity and paste a public or private Kinescope video link. Supported examples:

- https://kinescope.io/AbCd123xyz
- https://kinescope.io/AbCd123xyz?h=AccessCode
- https://kinescope.io/AbCd123xyz/AccessCode
- https://kinescope.io/embed/AbCd123xyz
- AbCd123xyz (standalone ID)

Only the Kinescope hostname is allowed, with HTTPS and a validated video ID or access code. Other URL parameters are discarded.

## Behavior

The AMD adapter uses the official Kinescope IFrame Player API and implements the Video Bridge player contract. It reports play, pause, time updates, seeks, completion, waiting and playback-rate changes. It implements programmatic play/pause, seek and playback-rate observation. Resume time is controlled by the Moodle consumer rather than Kinescope local storage.

Video Bridge owns course completion, progress persistence, anti-skip policies, analytics and grading. This source does not duplicate those responsibilities. Kinescope owns thumbnails and captions.

The Kinescope IFrame Player API is loaded from https://player.kinescope.io/latest/iframe.player.js. The Moodle site's Content Security Policy must allow the player script and Kinescope iframe endpoints. Browser playback sends network information to Kinescope, which may process viewing information independently of this subplugin.

## Development

Source: amd/src/player.js. The production AMD file is amd/build/player.min.js.

Execute Moodle PHPUnit tests using the site's configured PHPUnit runner, selecting videoprogresssource_kinescope/tests/plugin_test.php. For final acceptance, verify playback and progress against an accessible Kinescope video in a real Moodle activity.

References:
- https://github.com/yasha4u/moodle-media_kinescope
- https://docs.kinescope.com/player-docs/embedding/iframe-api/
- https://docs.kinescope.com/player-docs/embedding/iframe-api-control-player/

License: GPL v3 or later.

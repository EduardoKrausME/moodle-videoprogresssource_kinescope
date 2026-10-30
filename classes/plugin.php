<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kinescope video source.
 *
 * @package   videoprogresssource_kinescope
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_kinescope;

use context_module;
use local_video_bridge\source\plugin_base;
use moodle_exception;
use MoodleQuickForm;
use stdClass;

/**
 * Kinescope provider using its official IFrame Player API.
 */
class plugin extends plugin_base {
    /**
     * Returns the localized source name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'videoprogresssource_kinescope');
    }

    /**
     * Adds the Kinescope video URL field.
     *
     * @param MoodleQuickForm $mform Activity form.
     * @param string $sourcefield Source selector.
     * @return void
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sourcefield): void {
        $mform->addElement('url', 'kinescopeurl',
            get_string('videourl', 'videoprogresssource_kinescope'),
            ['size' => 80], ['usefilepicker' => false]);
        $mform->setType('kinescopeurl', PARAM_URL);
        $mform->addHelpButton('kinescopeurl', 'videourl', 'videoprogresssource_kinescope');
        $mform->hideIf('kinescopeurl', $sourcefield, 'neq', 'kinescope');
    }

    /**
     * Validates the submitted source URL.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Field errors.
     */
    public function validation(array $data, array $files): array {
        try {
            $this->build_config((object)$data);
            return [];
        } catch (moodle_exception $exception) {
            return ['kinescopeurl' => $exception->getMessage()];
        }
    }

    /**
     * Normalizes the video identifier and optional private-link hash.
     *
     * @param stdClass $data Submitted data.
     * @return array
     */
    public function build_config(stdClass $data): array {
        return self::extract_config(trim((string)($data->kinescopeurl ?? '')));
    }

    /**
     * Retains a canonical Kinescope URL for legacy consumers.
     *
     * @param array $config Source configuration.
     * @return string
     */
    public function get_legacy_value(array $config): string {
        return self::build_url($config);
    }

    /**
     * Restores the editable Kinescope URL.
     *
     * @param array $defaultvalues Form defaults.
     * @param context_module $context Module context.
     * @return void
     */
    public function prepare_form_data(array &$defaultvalues, context_module $context): void {
        $config = $this->decode_config((object)$defaultvalues);
        $defaultvalues['kinescopeurl'] = self::build_url($config);
    }

    /**
     * Returns player data restricted to a verified Kinescope URL.
     *
     * @param stdClass $activity Consumer record.
     * @param context_module $context Module context.
     * @return array
     */
    public function get_player_config(stdClass $activity, context_module $context): array {
        return [
            'url' => self::build_url($this->decode_config($activity)),
            'apiurl' => 'https://player.kinescope.io/latest/iframe.player.js',
        ];
    }

    /**
     * Supported operations are provided by the official IFrame Player API.
     *
     * @return array
     */
    public function get_capabilities(): array {
        return [
            'tracking' => true,
            'seeking' => true,
            'playbackcontrol' => true,
            'playbackrate' => true,
        ];
    }

    /**
     * Kinescope manages its own thumbnail.
     *
     * @return bool
     */
    public function supports_poster(): bool {
        return false;
    }

    /**
     * Subtitle tracks are managed by Kinescope.
     *
     * @return bool
     */
    public function supports_uploaded_captions(): bool {
        return false;
    }

    /**
     * Returns the template.
     *
     * @return string
     */
    public function get_player_template(): string {
        return 'videoprogresssource_kinescope/player';
    }

    /**
     * Returns the browser adapter.
     *
     * @return string
     */
    public function get_amd_module(): string {
        return 'videoprogresssource_kinescope/player';
    }

    /**
     * Converts an older URL or identifier to normalized configuration.
     *
     * @param string $legacyvalue Legacy source.
     * @return array
     */
    protected function get_legacy_config(string $legacyvalue): array {
        if (preg_match('/^([A-Za-z0-9]{3,128}):([A-Za-z0-9_-]{1,128})$/D', $legacyvalue, $matches)) {
            return ['id' => $matches[1], 'hash' => $matches[2]];
        }
        return self::extract_config($legacyvalue);
    }

    /**
     * Parses public and restricted URLs without accepting arbitrary hosts or queries.
     *
     * @param string $value URL or video identifier.
     * @return array
     */
    private static function extract_config(string $value): array {
        if (preg_match('/^[A-Za-z0-9]{3,128}$/D', $value)) {
            return ['id' => $value, 'hash' => ''];
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_kinescope');
        }

        $parts = parse_url($value);
        if (!is_array($parts) ||
                strtolower((string)($parts['scheme'] ?? '')) !== 'https' ||
                !in_array(strtolower((string)($parts['host'] ?? '')),
                    ['kinescope.io', 'www.kinescope.io'], true) ||
                isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_kinescope');
        }

        if (!preg_match('~^/(?:embed/)?([A-Za-z0-9]{3,128})(?:/([A-Za-z0-9_-]{1,128}))?/?$~D',
                (string)($parts['path'] ?? ''), $matches)) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_kinescope');
        }

        $hash = $matches[2] ?? '';
        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);
        if (isset($query['h'])) {
            if (!is_string($query['h']) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $query['h']) ||
                    ($hash !== '' && $hash !== $query['h'])) {
                throw new moodle_exception('invalidurl', 'videoprogresssource_kinescope');
            }
            $hash = $query['h'];
        }

        return ['id' => $matches[1], 'hash' => $hash];
    }

    /**
     * Produces a safe canonical URL even for restored backup configuration.
     *
     * @param array $config Video configuration.
     * @return string
     */
    private static function build_url(array $config): string {
        $id = (string)($config['id'] ?? '');
        $hash = (string)($config['hash'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{3,128}$/D', $id) ||
                ($hash !== '' && !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $hash))) {
            throw new moodle_exception('invalidurl', 'videoprogresssource_kinescope');
        }
        return 'https://kinescope.io/' . $id . ($hash !== '' ? '?h=' . rawurlencode($hash) : '');
    }
}

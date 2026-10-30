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
 * Tests for Kinescope provider.
 *
 * @package   videoprogresssource_kinescope
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace videoprogresssource_kinescope;

/**
 * Tests canonicalization and validation of untrusted Kinescope links.
 *
 * @covers \videoprogresssource_kinescope\plugin
 */
final class plugin_test extends \advanced_testcase {
    /**
     * Public and private URLs are normalized without untrusted URL components.
     */
    public function test_supported_urls(): void {
        $plugin = new plugin();
        $examples = [
            'AbCd123xyz' => 'https://kinescope.io/AbCd123xyz',
            'https://kinescope.io/AbCd123xyz' => 'https://kinescope.io/AbCd123xyz',
            'https://www.kinescope.io/AbCd123xyz/QwErT1' =>
                'https://kinescope.io/AbCd123xyz?h=QwErT1',
            'https://kinescope.io/embed/AbCd123xyz?h=Secret01&autoplay=1' =>
                'https://kinescope.io/AbCd123xyz?h=Secret01',
        ];
        foreach ($examples as $input => $expected) {
            $config = $plugin->build_config((object)['kinescopeurl' => $input]);
            $this->assertSame($expected, $plugin->get_legacy_value($config));
        }
    }

    /**
     * Reject other hosts, insecure URLs, malformed identifiers and hash arrays.
     */
    public function test_invalid_urls(): void {
        $plugin = new plugin();
        foreach ([
            'https://evil.example/AbCd123xyz',
            'http://kinescope.io/AbCd123xyz',
            'https://kinescope.io.evil.example/AbCd123xyz',
            'https://kinescope.io@evil.example/AbCd123xyz',
            'https://kinescope.io:8443/AbCd123xyz',
            'https://kinescope.io/AbCd123xyz?h[]=bad',
            'https://kinescope.io/AbCd123xyz/Private01?h=Other',
            'https://kinescope.io/AbCd123xyz/../../bad',
        ] as $url) {
            $this->assertArrayHasKey('kinescopeurl',
                $plugin->validation(['kinescopeurl' => $url], []), $url);
        }
    }

    /**
     * Broken persisted configuration cannot become a player URL.
     */
    public function test_invalid_persisted_config(): void {
        $plugin = new plugin();
        $this->expectException(\moodle_exception::class);
        $plugin->get_legacy_value(['id' => 'invalid/path', 'hash' => '']);
    }

    /**
     * The official IFrame API guarantees all four bridge capabilities.
     */
    public function test_capabilities(): void {
        $plugin = new plugin();
        foreach (['tracking', 'seeking', 'playbackcontrol', 'playbackrate'] as $capability) {
            $this->assertTrue($plugin->supports($capability));
        }
    }
}

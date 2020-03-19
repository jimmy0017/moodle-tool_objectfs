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
 * Class pusher_candidates
 * @package tool_objectfs
 * @author Gleimer Mora <gleimermora@catalyst-au.net>
 * @copyright Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_objectfs\local\object_manipulator\candidates;

use tool_objectfs\local\store\azure\client as azure_client;
use tool_objectfs\local\store\object_client_base;
use tool_objectfs\local\store\swift\client as swift_client;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/objectfs/tests/classes/test_client.php');

class pusher_candidates extends manipulator_candidates_base {

    /** @var array $filesystemmaxfilesizemap */
    private $filesystemmaxfilesizemap = [
        '\tool_objectfs\tests\test_file_system' => \tool_objectfs\tests\test_client::MAX_UPLOAD,
        '\tool_objectfs\digitalocean_file_system' => object_client_base::MAX_UPLOAD,
        '\tool_objectfs\s3_file_system' => object_client_base::MAX_UPLOAD,
        '\tool_objectfs\swift_file_system' => swift_client::MAX_UPLOAD,
    ];

    /** @var string $queryname */
    protected $queryname = 'get_push_candidates';

    /**
     * @inheritDoc
     * @return string
     */
    public function get_candidates_sql() {
        return 'SELECT MAX(f.id),
                       f.contenthash,
                       MAX(f.filesize) AS filesize
                  FROM {files} f
                  JOIN {tool_objectfs_objects} o ON f.contenthash = o.contenthash
                 WHERE f.filesize > :threshold
                   AND f.filesize < :maximum_file_size
                   AND f.timecreated <= :maxcreatedtimstamp
                   AND o.location = :object_location
              GROUP BY f.contenthash, o.location';
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function get_candidates_sql_params() {
        return [
            'maxcreatedtimstamp' => time() - $this->config->minimumage,
            'threshold' => $this->config->sizethreshold,
            'maximum_file_size' => $this->get_mmaxfilesize(),
            'object_location' => OBJECT_LOCATION_LOCAL,
        ];
    }

    /**
     * @return float|int
     */
    private function get_mmaxfilesize() {
        if (empty($this->config->filesystem)) {
            return \tool_objectfs\tests\test_client::MAX_UPLOAD;
        }

        if (class_exists('\MicrosoftAzure\Storage\Common\Internal\Resources')) {
            $this->filesystemmaxfilesizemap['\tool_objectfs\azure_file_system'] = azure_client::MAX_UPLOAD;
        }

        return $this->filesystemmaxfilesizemap[$this->config->filesystem];
    }
}

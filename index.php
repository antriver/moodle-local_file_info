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
 * Display a list of the largest files.
 *
 * @package    local_file_info
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(__DIR__)).'/config.php');

$report = optional_param('report', '', PARAM_RAW);

$limit = 100;

echo $OUTPUT->header();

?>
<div class="text-center">
    <p>Select a report.</p>
    <a class="btn <?=($report === 'files' ? 'active' : '')?>" href="?report=files">Largest Files</a>
    <a class="btn <?=($report === 'users' ? 'active' : '')?>" href="?report=users">Largest Users</a>
    <a class="btn <?=($report === 'areas' ? 'active' : '')?>" href="?report=areas">Largest Areas</a>
</div>
<?php

/**
 * Display an array of files in a table.
 *
 * @param array $files
 */
function list_files(array $files) {
    echo '<table class="table">
        <tr>
            <th>File ID</th>
            <th>Date</th>
            <th>Filename</th>
            <th>File Size</th>
            <th>User ID</th>
            <th>Username</th>
            <th>Component</th>
            <th>File Area</th>
            <th>Item ID</th>
        </tr>';
    foreach ($files as $file) {
        $filesize = $file->filesize / 1024 / 1024;
        echo "<tr>
            <td>{$file->id}</td>
            <td>".date('Y-m-d', $file->timecreated)."</td>
            <td>{$file->filename}</td>
            <td>".number_format($filesize, 2)." MB</td>
            <td>{$file->userid}</td>
            <td>{$file->username}</td>
            <td>{$file->component}</td>
            <td>{$file->filearea}</td>
            <td>{$file->itemid}</td>
        </tr>";
    }
    echo '</table>';
}

switch ($report) {

    case 'files':
        echo '<h2>'.$limit.' Largest Files</h2>';
        $files = $DB->get_records_sql('
            SELECT f.*, u.username FROM {files} f
            LEFT JOIN {user} u on u.id = f.userid
            ORDER BY filesize DESC
            LIMIT '.$limit.'
        ');

        list_files($files);
        break;

    case 'userfiles':
        echo '<h2>User\'s Files</h2>';
        $userid = required_param('userid', PARAM_INT);
        $files = $DB->get_records_sql('
            SELECT f.*, u.username FROM {files} f
            WHERE f.userid = ?
            LEFT JOIN {user} u on u.id = f.userid
            ORDER BY filesize DESC',
            [
                $userid
            ]
        );
        list_files($files);
        break;

    case 'users';
        echo '<h2>'.$limit.' Largest Users</h2>';
        $users = $DB->get_records_sql('
            SELECT
              COUNT(f.id) AS files,
              SUM(f.filesize) AS totalsize,
              u.id,
              u.username
            FROM {files} f
            LEFT JOIN {user} u on u.id = f.userid
            GROUP BY u.id
            ORDER BY totalsize DESC
            LIMIT '.$limit.'
            ');
        ?>
        <table class="table">
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Files</th>
                <th>Total Size</th>
            </tr>
            <?php
            foreach ($users as $user) {
                $totalsize = $user->totalsize / 1024 / 1024;
                echo "<tr>
                    <td>{$user->id}</td>
                    <td>{$user->username}</td>
                    <td>".number_format($user->files)."</td>
                    <td>".number_format($totalsize, 2)." MB</td>
                </tr>";
            }
            ?>
        </table>
        <?php
        break;

    case 'areas':
        echo '<h2>'.$limit.' Largest Areas</h2>';
        $areas = $DB->get_records_sql('
            SELECT
              -- unique first column for moodle
              CONCAT(component, filearea, contextid, ctx.contextlevel, ctx.instanceid, itemid) AS id,
              COUNT(f.id) AS files,
              SUM(f.filesize) AS totalsize,
              f.component,
              f.filearea,
              f.contextid,
              f.itemid,
              ctx.contextlevel AS ctxlevel,
              ctx.instanceid AS ctxinstanceid
            FROM {files} f
            LEFT JOIN {context} ctx ON ctx.id = f.contextid
            GROUP BY component, filearea, contextid, ctx.contextlevel, ctx.instanceid, itemid
            ORDER BY totalsize DESC
            LIMIT '.$limit.'
            ');
        ?>
        <table class="table">
            <tr>
                <th>Component</th>
                <th>Section</th>
                <th>Context ID</th>
                <th>Context</th>
                <th>Files</th>
                <th>Total Size</th>
            </tr>
            <?php
            foreach ($areas as $area) {
                $totalsize = $area->totalsize / 1024 / 1024;

                $contextname = '';
                if ($area->contextid) {
                    $context = context_helper::instance_by_id($area->contextid);
                    if ($context instanceof context) {
                        $contextname .= '<small>Instancce ID '.$context->instanceid.'</small><br/>'.$context->get_context_name();
                        if ($parentcontext = $context->get_parent_context()) {
                            $contextname .= '<br/>'.$parentcontext->get_context_name();
                        }
                    }
                }

                echo "<tr>
                    <td>{$area->component}</td>
                    <td>{$area->filearea}</td>
                    <td>{$area->contextid}</td>
                    <td>{$contextname}</td>
                    <td>".number_format($area->files)."</td>
                    <td>".number_format($totalsize, 2)." MB</td>
                </tr>";
            }
            ?>
        </table>
        <?php
        break;
}

echo $OUTPUT->footer();


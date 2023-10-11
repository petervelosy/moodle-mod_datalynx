<?php
// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynxfield
 * @subpackage file
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_file_renderer extends datalynxfield_renderer {

    /**
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        // If we see a 0 in content there are no files stored. Create new draft area.
        $content = $entry->{"c{$fieldid}_content"} ?? null;
        if ($content == 0 || !isset($entry->{"c{$fieldid}_id"})) {
            $contentid = null;
        } else {
            $contentid = $entry->{"c{$fieldid}_id"};
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        $fmoptions = array('subdirs' => 0, 'maxbytes' => $field->get('param1'),
                'maxfiles' => $field->get('param2'),
                'accepted_types' => explode(',', $field->get('param3')));

        // Redundant draft areas are cleaned by the cronjob eventually.
        $draftitemid = file_get_submitted_draft_itemid("{$fieldname}_filemanager");

        file_prepare_draft_area($draftitemid, $field->df()->context->id, 'mod_datalynx', 'content',
                $contentid, $fmoptions);

        // For behat testing: Much, much better to use the official step there than a bunch of very volatile js/css lines.
        $label = $field->df->name() == "Datalynx Test Instance" ? "File" : "";
        // File manager.
        $mform->addElement('filemanager', "{$fieldname}_filemanager", $label, null, $fmoptions);
        $mform->setDefault("{$fieldname}_filemanager", $draftitemid);
        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule("{$fieldname}_filemanager", null, 'required', null, 'client');
        }
    }

    /**
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = $entry->{"c{$fieldid}_content"} ?? null;
        $content1 = $entry->{"c{$fieldid}_content1"} ?? null;
        $content2 = $entry->{"c{$fieldid}_content2"} ?? null;
        $contentid = $entry->{"c{$fieldid}_id"} ?? null;

        if (empty($content)) {
            return '';
        }

        if (!empty($params['downloadcount'])) {
            return $content2;
        }

        $fs = get_file_storage();

        // Contentid is stored in itemid for lookup. This is usable with fieldgroups.
        $files = $fs->get_area_files($field->df()->context->id, 'mod_datalynx', 'content',
                $contentid);
        if (!$files or !(count($files) > 1)) {
            return '';
        }

        $strfiles = array();
        foreach ($files as $file) {
            if (!$file->is_directory()) {

                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $filenameinfo = pathinfo($filename);
                $path = "/{$field->df()->context->id}/mod_datalynx/content/$contentid";
                // ToDo: Remove or implement altname.
                $altname = "";
                $strfiles[] = $this->display_file($file, $path, $altname, $params);
            }
        }

        // For csv export we simply show link to first file.
        if ($exportcsv = optional_param('exportcsv', '', PARAM_ALPHA)) {
            return $this->render_csv($strfiles);
        }

        return implode("<br />\n", $strfiles);
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();

        if ($mform->_formName == 'mod_datalynx_customfilter_frontend_form') {
            $options = array(
                    -1  => get_string('choose'),
                    1 => get_string('filemissing', 'datalynx'),
                    0 => get_string('fileexist', 'datalynx')
            );
            $arr[] = $mform->createElement('select', $fieldname, '', $options);
            $mform->setType($fieldname, PARAM_INT);
            $mform->setDefault($fieldname, $value);
        } else {
            $arr[] = &$mform->createElement('text', $fieldname, null, array('size' => '32'));
            $mform->setType($fieldname, PARAM_NOTAGS);
            $mform->setDefault($fieldname, $value ? 1 : 0);
            $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        }
        return array($arr, null);
    }

    /**
     */
    protected function display_file($file, string $path, string $altname = '', ?array $params = null) {
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_datalynx/pdfembed', 'renderPDF', ['http://localhost/401_moodle/pluginfile.php/14444/mod_resource/content/1/%28SDAW%29%20Search%20on%20Commons%20Research%20Report%20%28June%202020%29.pdf', 'maincontent']);

        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();
        $pluginfileurl = '/pluginfile.php';

        if ($mimetype === 'application/pdf') {
            // PDF document
            $moodleurl = moodle_url::make_file_url($pluginfileurl, "$path/$filename");
            return $this->embed_pdf($moodleurl->out(), $filename, "Click");
        }

        if (!empty($params['url'])) {
            return moodle_url::make_file_url($pluginfileurl, "$path/$filename");
        } else {
            if (!empty($params['size'])) {
                $bsize = $file->get_filesize();
                if ($bsize < 1000000) {
                    $size = round($bsize / 1000, 1) . 'KB';
                } else {
                    $size = round($bsize / 1000000, 1) . 'MB';
                }
                return $size;
            } else {
                if (!empty($params['content'])) {
                    return $file->get_content();
                } else {
                    return $this->display_link($file, $path, $altname, $params);
                }
            }
        }
    }

    /**
     * Returns general link or pdf embedding html.
     * @param string $fullurl
     * @param string $title
     * @param string $clicktoopen
     * @return string html
     */
    protected function embed_pdf(string $fullurl): string {
        global $PAGE;
        $html = <<<EOT
<div class="resourcecontent resourcepdf">
  <object id="resourceobject" data="$fullurl" type="application/pdf" width="800" height="600">
    <param name="src" value="$fullurl" />
  </object>
</div>
EOT;
        /**
        $html = '<div class="resourcecontent resourcepdf w-100">
  <object id="resourceembed" data="' . $fullurl . '" type="application/pdf" width="800" height="600">
  <p>Unable to display PDF file. <a href="' . $fullurl . '">Download</a> instead.</p>
</div>';
        **/

        // the size is hardcoded in the boject obove intentionally because it is adjusted by the following function on-the-fly
        $PAGE->requires->js_call_amd('mod_datalynx/maximiseembed', 'initMaximisedEmbed', ['resourceembed']);
        return $html;
    }

    /**
     */
    protected function display_link($file, $path, $altname, $params = null): string {
        global $OUTPUT;

        $filename = $file->get_filename();
        $displayname = $altname ?: $filename;
        $fileicon = html_writer::empty_tag('img',
                array('src' => $OUTPUT->image_url(file_mimetype_icon($file->get_mimetype())),
                        'alt' => $file->get_mimetype(), 'height' => 16, 'width' => 16));

        if (!empty($params['download'])) {
            list(, $context, , , $contentid) = explode('/', $path);
            $url = new moodle_url("/mod/datalynx/field/file/download.php",
                    array('cid' => $contentid, 'context' => $context, 'file' => $filename));
        } else {
            $url = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
        }

        return html_writer::link($url, "$fileicon&nbsp;$displayname");
    }

    /**
     */
    public function pluginfile_patterns(): array {
        return array("[[{$this->_field->name()}]]");
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns(): array {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:url]]"] = array(false);
        $patterns["[[$fieldname:alt]]"] = array(true);
        $patterns["[[$fieldname:size]]"] = array(false);
        $patterns["[[$fieldname:content]]"] = array(false);
        $patterns["[[$fieldname:download]]"] = array(false);
        $patterns["[[$fieldname:downloadcount]]"] = array(false);

        return $patterns;
    }

    /**
     * Returns comma seperated list of urls in this entry.
     */
    public function render_csv($strfiles): string {
        $regex = '/https?\:\/\/[^\" ]+/i';
        $matches = array();
        foreach ($strfiles as $strfile) {
            preg_match($regex, $strfile, $match);
            $matches[] = $match[0];
        }

        return implode(",", $matches);
    }
}

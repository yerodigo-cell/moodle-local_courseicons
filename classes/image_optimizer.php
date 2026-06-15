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
 * Image optimizer class for local_courseicons.
 *
 * @package    local_courseicons
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseicons;

defined('MOODLE_INTERNAL') || die();

/**
 * Class image_optimizer
 *
 * @package    local_courseicons
 */
class image_optimizer {
    /**
     * The maximum dimension (width or height) in pixels.
     */
    public const MAX_DIMENSION = 256;

    /**
     * Optimizes all image files in a specific file area.
     *
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @return void
     */
    public static function optimize_area_files(int $contextid, string $component, string $filearea, int $itemid): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'id', false);

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $mimetype = $file->get_mimetype();
            if (!in_array($mimetype, ['image/jpeg', 'image/png', 'image/webp'])) {
                continue;
            }

            // Extract content and build image object.
            $content = $file->get_content();
            $img = @imagecreatefromstring($content);

            if ($img !== false) {
                $width = imagesx($img);
                $height = imagesy($img);

                $newwidth = $width;
                $newheight = $height;

                if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                    $ratio = $width / $height;
                    if ($ratio > 1) {
                        $newwidth = self::MAX_DIMENSION;
                        $newheight = (int)(self::MAX_DIMENSION / $ratio);
                    } else {
                        $newheight = self::MAX_DIMENSION;
                        $newwidth = (int)(self::MAX_DIMENSION * $ratio);
                    }
                }

                // Create a new true color image.
                $newimg = imagecreatetruecolor($newwidth, $newheight);

                // Preserve transparency for PNG and WebP.
                if ($mimetype === 'image/png' || $mimetype === 'image/webp') {
                    imagealphablending($newimg, false);
                    imagesavealpha($newimg, true);
                    $transparent = imagecolorallocatealpha($newimg, 255, 255, 255, 127);
                    imagefilledrectangle($newimg, 0, 0, $newwidth, $newheight, $transparent);
                }

                imagecopyresampled($newimg, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

                // Buffer the output.
                ob_start();
                if ($mimetype === 'image/jpeg') {
                    imagejpeg($newimg, null, 80); // 80% quality
                } else if ($mimetype === 'image/png') {
                    imagepng($newimg, null, 8); // Compression level 8 (0-9)
                } else if ($mimetype === 'image/webp') {
                    imagewebp($newimg, null, 80); // 80% quality
                }
                $optimizedcontent = ob_get_clean();

                imagedestroy($img);
                imagedestroy($newimg);

                // Only replace if it's actually smaller or resized.
                if (strlen($optimizedcontent) < strlen($content) || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                    $filerecord = [
                        'contextid' => $file->get_contextid(),
                        'component' => $file->get_component(),
                        'filearea'  => $file->get_filearea(),
                        'itemid'    => $file->get_itemid(),
                        'filepath'  => $file->get_filepath(),
                        'filename'  => $file->get_filename(),
                    ];

                    $file->delete();
                    $fs->create_file_from_string($filerecord, $optimizedcontent);
                }
            }
        }
    }
}

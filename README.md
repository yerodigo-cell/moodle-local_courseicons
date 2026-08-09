# Course Activity Icons (local_courseicons)

A local plugin for Moodle that allows teachers to customize the icons of activities and resources in their courses, replacing the default ones with custom images (SVG, PNG, JPG, or GIF).

## How to use it

1. Enter any course where you have editing permissions.

2. In the course navigation menu, click on **Customize activity icons**.

3. You will see the list of course activities. Click on **Change icon** for the one you want to modify.

4. Upload your file (SVG, PNG, JPG, or GIF) and save. That's it!

### Global Settings for Administrators
Administrators can set default icons for activity types across the entire site by navigating to:
**Site Administration > Plugins > Local plugins > Global icon settings**.

## Icon Hierarchy

When an activity is displayed in a course, the plugin resolves which custom icon to show based on the following precedence (highest to lowest):

1. **Individual Icon:** Set by a teacher for a specific activity instance in their course.
2. **Course Default Icon:** Set by a teacher for all activities of that type within their course.
3. **Global Default Icon:** Set by the administrator for the entire site.
4. **Moodle Default:** The original Moodle icon is shown if none of the above are configured.

## Pro Tip for Images

While the images **do not strictly need to be square** (the plugin uses smart scaling to prevent distortion), using a **1:1 square ratio** is highly recommended. This ensures the icon perfectly fills the circular or square containers without leaving empty gaps.

## Key Features

* **Global Defaults:** Administrators can set a global default icon for all courses.

* **Course Defaults:** Teachers can set a default icon for an entire activity type (e.g., all Assignments or Quizzes) in one click. Individual icons can still overwrite defaults.

* **Bulk Upload:** Update multiple activities at once. Select several items and change all their icons with a single upload.

* **Automatic Image Optimization:** Automatically resizes large images and compresses JPG, PNG, and WebP files to save space and boost performance, keeping course loading speeds blazing fast.

* **Smart Adaptability:** The plugin detects the course format (including specialized formats like Format Tiles) to match the native size and style of the icons perfectly.

* **Animation Support:** Allows uploading animated GIFs to bring more life and dynamism to your courses.

*Developed to improve the visual experience in Moodle.*

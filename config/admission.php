<?php

/*
 | Temporary branding values. From milestone 4 the institute name, logo, address,
 | phone, e-mail and website are read from the institutions table instead.
 */
return [
    'brand' => env('ADMISSION_BRAND', 'Soft Info IT'),
    'powered_by' => env('ADMISSION_POWERED_BY', 'softinfoit.com'),
    'institute_name' => env('INSTITUTE_NAME', 'Your Institute Name'),

    // 11-character YouTube video id shown on the login page; leave empty to hide the video.
    'guide_video_id' => preg_match('/^[A-Za-z0-9_-]{11}$/', (string) env('GUIDE_VIDEO_ID', '')) ? env('GUIDE_VIDEO_ID') : null,
];

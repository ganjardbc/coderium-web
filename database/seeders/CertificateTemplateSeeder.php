<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CertificateTemplate::create([
            'name' => 'Default Certificate Template',
            'description' => 'Standard certificate template for course completion',
            'template_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .certificate { border: 5px solid #0066cc; padding: 40px; max-width: 800px; margin: 0 auto; }
        .title { font-size: 36px; font-weight: bold; color: #0066cc; margin-bottom: 20px; }
        .subtitle { font-size: 24px; margin-bottom: 30px; }
        .name { font-size: 32px; font-weight: bold; color: #333; margin: 20px 0; }
        .course { font-size: 20px; font-style: italic; margin: 20px 0; }
        .date { font-size: 16px; margin-top: 40px; }
        .number { font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="title">CERTIFICATE OF COMPLETION</div>
        <div class="subtitle">This is to certify that</div>
        <div class="name">{{user_name}}</div>
        <div class="subtitle">has successfully completed the course</div>
        <div class="course">{{track_title}}</div>
        <div class="date">Completed on {{completion_date}}</div>
        <div class="number">Certificate Number: {{certificate_number}}</div>
    </div>
</body>
</html>',
            'is_default' => true,
        ]);
    }
}

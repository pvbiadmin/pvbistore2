<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>GCash Payment Message</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .email-header {
            text-align: center;
            background-color: #4CAF50;
            color: #ffffff;
            padding: 10px 0;
            border-radius: 8px 8px 0 0;
        }

        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .email-body {
            padding: 20px;
        }

        .email-body p {
            font-size: 16px;
            line-height: 1.5;
            margin: 15px 0;
        }

        .email-footer {
            margin-top: 20px;
            text-align: center;
            color: #777;
            font-size: 12px;
        }

        .contact-info {
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .contact-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="email-header">
        <h1>GCash Payment Message</h1>
    </div>

    <div class="email-body">
        <p><strong>Subject:</strong> {{ $subject }}</p>
        <p><strong>Message:</strong></p>
        <p>{!! $contactMessage !!}</p>

        <div class="contact-info">
            <p><strong>From:</strong> {{ $email }}</p>
        </div>
    </div>

    <div class="email-footer">
        <p>This message was sent via your website's contact form.</p>
    </div>
</div>
</body>
</html>


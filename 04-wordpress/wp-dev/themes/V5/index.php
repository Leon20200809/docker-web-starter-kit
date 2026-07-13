<?php

/**
 * LazyGeniusDev_WordPressThemeV5 - Connection & Environment Test
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>V5 Theme Active Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f2f5;
            color: #1c1e21;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #0073aa;
            margin-top: 0;
            font-size: 1.8rem;
        }

        .status {
            background: #edfae1;
            color: #4a7a1a;
            padding: 0.75rem;
            border-radius: 6px;
            font-weight: bold;
            margin: 1rem 0;
            border-left: 4px solid #4a7a1a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            text-align: left;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        th {
            color: #666;
            font-weight: 500;
            width: 35%;
        }
    </style>
</head>

<body>

    <div class="card">
        <h1>🚀 V5 Theme Environment Connection</h1>
        <div class="status">✓ Docker Mount & WordPress Active Success!</div>

        <table>
            <tr>
                <th>Site Name</th>
                <td><?php bloginfo('name'); ?></td>
            </tr>
            <tr>
                <th>PHP Version</th>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <th>DB Host</th>
                <td><?php echo defined('DB_HOST') ? DB_HOST : 'Unknown'; ?></td>
            </tr>
            <tr>
                <th>Active Theme</th>
                <td>LazyGeniusDev_V5</td>
            </tr>
        </table>
    </div>

</body>

</html>
<?php

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="lead-import-template.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'fullName',
    'email',
    'phone',
    'source',
    'orgName'
]);

fputcsv($output, [
    'Rahul Sharma',
    'rahul@example.com',
    '9876543210',
    'Website',
    'TEST COMPANY'
]);

fputcsv($output, [
    'Priya Verma',
    'priya@example.com',
    '9876501234',
    'Instagram',
    'DEMO COMPANY'
]);

fclose($output);
exit;
<!doctype html>
<html lang="en" data-bs-theme="<?php echo e(dm_current_theme()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?php echo e(dm_current_theme() === 'dark' ? '#15212c' : '#edf1f3'); ?>">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($title); ?> · DorMate</title>
    <link rel="icon" href="<?php echo e(asset('assets/dormate-logo.webp')); ?>" type="image/webp">
    <link rel="stylesheet" href="<?php echo e(asset('assets/bootstrap.min.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('assets/app.css')); ?>">
    <?php if($stylesheet): ?><link rel="stylesheet" href="<?php echo e(asset('assets/'.$stylesheet)); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(asset('assets/theme.css')); ?>">
</head>
<body><a class="skip-link" href="#main">Skip to content</a>
<?php /**PATH C:\Users\USER\DorMate_Web_Laravel\DorMate-Laravel\resources\views/layouts/head.blade.php ENDPATH**/ ?>
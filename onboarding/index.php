<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/models/OnboardingModel.php';
require_once __DIR__ . '/controllers/OnboardingController.php';

$onboardingController = new OnboardingController(new OnboardingModel($onboardingDb));
$onboardingPage = $onboardingController->resolvePage($_GET['page'] ?? 'dashboard');
$onboardingData = $onboardingController->buildPageData($onboardingPage);
$onboardingData['canManage'] = $onboardingCanManage;
$onboardingData['submissionNotice'] = $_SERVER['REQUEST_METHOD'] === 'POST'
	? 'El proceso no se guardó porque la tabla de almacenamiento de onboarding aún no está definida.'
	: '';
$onboardingUser = $_SESSION['user'];

require __DIR__ . '/views/layout.php';
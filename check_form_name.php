<?php

use App\Form\ProviderCvType;
use App\Entity\Provider;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

require __DIR__ . '/vendor/autoload.php';

// Minimal form factory setup
$formFactory = Forms::createFormFactoryBuilder()
    ->addExtension(new HttpFoundationExtension())
    ->getFormFactory();

$provider = new Provider();
$form = $formFactory->create(ProviderCvType::class, $provider);

echo "Form Name: " . $form->getName() . "\n";

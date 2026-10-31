<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Rule\Rules\Class_\ClassNameMustHaveSuffixRule;
use Boundwize\StructArmed\Rule\Rules\Class_\MustBeFinalRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\ClassKindConventionsRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\ClassMustDeclareApiOrInternalRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\InternalClassMustBeFinalRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\InternalIsUsableOnlyWithinItsModuleRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\InternalMustLiveInInternalNamespaceRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\InternalMustNotLeakIntoPublicSignatureRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\InternalNamespaceMustBeAnnotatedInternalRule;
use Spiral\RoadRunnerBridge\Tools\StructArmed\Rule\MustExtendRule;

// Layers that wire the modules together: they may reach into any module's internals.
$compositionRoots = ['Bootloader', 'BootloaderInternal'];

return Architecture::define()
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())

    ->rule(
        'tests_classes.must_be_final',
        new MustBeFinalRule(layer: 'tests')
    )
    ->layer('tests', 'tests/src')

    // Encapsulation: who may see what.
    ->layer('Bridge', 'src')
    ->rule('bridge.class_must_declare_api_or_internal', new ClassMustDeclareApiOrInternalRule('Bridge'))
    ->rule('bridge.internal_namespace_must_be_annotated_internal', new InternalNamespaceMustBeAnnotatedInternalRule('Bridge'))
    ->rule('bridge.internal_must_live_in_internal_namespace', new InternalMustLiveInInternalNamespaceRule('Bridge'))
    ->rule('bridge.internal_class_must_be_final', new InternalClassMustBeFinalRule('Bridge'))
    ->rule('bridge.internal_must_not_leak_into_public_signature', new InternalMustNotLeakIntoPublicSignatureRule('Bridge'))
    ->rule(
        'bridge.internal_is_usable_only_within_its_module',
        new InternalIsUsableOnlyWithinItsModuleRule('Bridge', $compositionRoots),
    )

    // Conventions per kind of class.
    ->rule('bootloader.must_be_final', new MustBeFinalRule('Bootloader'))
    ->rule('bootloader.must_have_suffix', new ClassNameMustHaveSuffixRule('Bootloader', 'Bootloader'))
    ->rule('bootloader.must_extend_bootloader', new MustExtendRule('Bootloader', [Spiral\Boot\Bootloader\Bootloader::class]))
    ->rule('commands.conventions', new ClassKindConventionsRule('Bridge', 'Command', [
        Spiral\Console\Command::class,
        Spiral\Scaffolder\Command\AbstractCommand::class,
    ]))
    ->rule('config.must_be_final', new MustBeFinalRule('Config'))
    ->rule('exceptions.conventions', new ClassKindConventionsRule('Bridge', 'Exception', [
        Exception::class,
        Spiral\Boot\Exception\BootException::class,
    ], segment: 'Exception'))

    // Dependency direction between modules.
    ->layer('RoadRunnerMode', 'src/RoadRunnerMode.php')
    ->layer('Exception', 'src/Exception')
    ->layer('Logger', 'src/Logger')
    ->layer('Http', 'src/Http')
    ->layer('Centrifugo', 'src/Centrifugo', 'src/Centrifugo/Internal')
    ->layer('CentrifugoInternal', 'src/Centrifugo/Internal')
    ->layer('GRPC', 'src/GRPC', 'src/GRPC/Internal')
    ->layer('GRPCInternal', 'src/GRPC/Internal')
    ->layer('Queue', 'src/Queue', 'src/Queue/Internal')
    ->layer('QueueInternal', 'src/Queue/Internal')
    ->layer('Config', 'src/Config')
    ->layer('Scaffolder', 'src/Scaffolder', 'src/Scaffolder/Internal')
    ->layer('ScaffolderInternal', 'src/Scaffolder/Internal')
    ->layer('Bootloader', 'src/Bootloader', 'src/Bootloader/Internal')
    ->layer('BootloaderInternal', 'src/Bootloader/Internal')
    ->ruleset([
        'RoadRunnerMode'     => [],
        'Exception'          => [],
        'Logger'             => [],
        'Http'               => ['RoadRunnerMode'],
        'Centrifugo'         => [],
        'CentrifugoInternal' => ['Centrifugo', 'RoadRunnerMode'],
        'GRPC'               => [],
        'GRPCInternal'       => ['Config', 'GRPC', 'RoadRunnerMode'],
        'Queue'              => [],
        'QueueInternal'      => ['Config', 'Queue', 'RoadRunnerMode'],
        'Config'             => ['Centrifugo', 'GRPC'],
        'Scaffolder'         => ['Centrifugo'],
        'ScaffolderInternal' => ['+Scaffolder'],
        'BootloaderInternal' => ['Exception', 'CentrifugoInternal', 'GRPCInternal', '+Http', 'QueueInternal'],
        'Bootloader'         => ['+BootloaderInternal', '+GRPCInternal', '+QueueInternal', '+ScaffolderInternal', 'Logger'],
    ]);

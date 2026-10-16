<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Rule\Rules\Class_\MustBeFinalRule;

return Architecture::define()
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())

    ->rule(
        'tests_classes.must_be_final',
        new MustBeFinalRule(layer: 'tests')
    )
    ->layer('tests', 'tests/src')

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
    ->layer('Tcp', 'src/Tcp', 'src/Tcp/Internal')
    ->layer('TcpInternal', 'src/Tcp/Internal')
    ->layer('Config', 'src/Config')
    ->layer('Scaffolder', 'src/Scaffolder')
    ->layer('Console', 'src/Console')
    ->layer('FallbackDispatcher', 'src/FallbackDispatcher.php')
    ->layer('Bootloader', 'src/Bootloader')
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
        'Tcp'                => [],
        'TcpInternal'        => ['Config', 'Tcp', 'RoadRunnerMode'],
        'Config'             => ['Centrifugo', 'GRPC', 'Tcp'],
        'Scaffolder'         => ['Centrifugo', 'Tcp'],
        'Console'            => ['+GRPCInternal', 'Queue', 'Scaffolder'],
        'FallbackDispatcher' => ['Exception', 'CentrifugoInternal', 'GRPCInternal', '+Http', 'QueueInternal', 'TcpInternal'],
        'Bootloader'         => ['+Console', '+FallbackDispatcher', '+Scaffolder', 'Logger'],
    ]);

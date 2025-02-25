<?php

namespace Spiral\RoadRunnerBridge\Logger;

/**
 * @see https://docs.roadrunner.dev/docs/logging-and-observability/logger#modes
 */
enum RoadRunnerLogsMode: string
{
    case Production = 'production';
    case Development = 'development';
}

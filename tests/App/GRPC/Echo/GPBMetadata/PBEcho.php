<?php

declare(strict_types=1);
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: echo.proto

namespace Spiral\App\GRPC\Echo\GPBMetadata;

class PBEcho
{
    public static $is_initialized = false;

    public static function initOnce()
    {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
            return;
        }
        $pool->internalAddGeneratedFile(hex2bin(
            '0aa7010a0a6563686f2e70726f746f12077365727669636522160a074d65' .
            '7373616765120b0a036d736718012001280932340a044563686f122c0a04' .
            '50696e6712102e736572766963652e4d6573736167651a102e7365727669' .
            '63652e4d6573736167652200423aca021453706972616c5c4170705c4752' .
            '50435c4563686fe2022053706972616c5c4170705c475250435c4563686f' .
            '5c4750424d65746164617461620670726f746f33'
        ));

        static::$is_initialized = true;
    }
}

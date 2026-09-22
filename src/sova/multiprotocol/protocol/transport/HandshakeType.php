<?php

declare(strict_types=1);

namespace sova\multiprotocol\protocol\transport;

enum HandshakeType
{
	case NETWORK_SETTINGS;
	case LEGACY_LOGIN;
}

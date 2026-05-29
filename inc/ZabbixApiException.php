<?php
/**
 * zabbix-pdf-report — 2.x
 * Typed exception for Zabbix API errors.
 *
 * @package   zabbix-pdf-report
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

class ZabbixApiException extends RuntimeException
{
    /** @var array<string,mixed>|null Raw JSON-RPC error object as returned by Zabbix. */
    private ?array $rpcError;

    /**
     * @param array<string,mixed>|null $rpcError The full JSON-RPC `error` object, if any.
     */
    public function __construct(string $message, ?array $rpcError = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->rpcError = $rpcError;
    }

    /** @return array<string,mixed>|null */
    public function getRpcError(): ?array
    {
        return $this->rpcError;
    }
}

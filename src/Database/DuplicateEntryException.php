<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Lancada por database::query() quando o MySQL rejeita uma insercao/update
 * por violacao de UNIQUE ou por SIGNAL de trigger anti-duplicidade.
 *
 * Permite que a camada de servico distinga "duplicidade" de "erro generico"
 * sem depender de mensagens de erro (que podem variar entre versoes do MySQL).
 *
 * Codigos detectados pelo database::isDuplicateError():
 *   - SQLSTATE '23000' (integrity constraint violation)
 *   - SQLSTATE '45000' (SIGNAL custom das triggers de anti-duplicidade)
 *   - errno 1062 (Duplicate entry — legacy MySQL)
 *
 * @see \database::isDuplicateError()
 */
class DuplicateEntryException extends \RuntimeException
{
}

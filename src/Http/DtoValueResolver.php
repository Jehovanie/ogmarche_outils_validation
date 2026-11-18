<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Remplit les arguments de controleur du namespace App\Dto depuis le corps JSON de la requete
 * puis les valide (equivalent de #[MapRequestPayload], absent de Symfony 5.4). Un corps qui ne
 * respecte pas les types ou les contraintes donne une reponse 422 (voir DtoInvalideSubscriber).
 */
final class DtoValueResolver implements ArgumentValueResolverInterface
{
    private const NAMESPACE_DTO = 'App\\Dto\\';

    private SerializerInterface $serializer;
    private ValidatorInterface $validator;

    public function __construct(SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return null !== $argument->getType() && 0 === strpos($argument->getType(), self::NAMESPACE_DTO);
    }

    /** @return iterable<object> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $corps = trim($request->getContent());
        if ('' !== $corps) {
            $donnees = json_decode($corps, true);
            if (!\is_array($donnees)) {
                throw new BadRequestHttpException('Corps JSON invalide.');
            }
        }

        try {
            $dto = $this->serializer->deserialize('' === $corps ? '{}' : $corps, $argument->getType(), 'json');
        } catch (ExceptionInterface $e) {
            throw new DtoInvalideException([['propertyPath' => '', 'title' => 'Données invalides : '.$e->getMessage()]]);
        }

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
            throw DtoInvalideException::depuis($violations);
        }

        yield $dto;
    }
}

<?php

declare(strict_types=1);

namespace Craft\Assistant\Controller;

use Craft\Assistant\Ai\QueryAnswerer;
use Craft\Assistant\Ai\QuotationAssistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatController extends AbstractController
{
    #[Route('/', name: 'app_chat', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    #[Route('/api/question', name: 'app_api_question', methods: ['POST'])]
    public function question(
        Request $request,
        QueryAnswerer $answerer,
        QuotationAssistant $assistant,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $question = trim((string) ($payload['question'] ?? ''));

        if ('' === $question) {
            return new JsonResponse(['error' => 'Empty question.'], 400);
        }

        $mode = (string) ($payload['mode'] ?? 'query');

        if ('quotation' === $mode) {
            $answer = $assistant->createDraft(
                $question,
                (string) ($payload['customer'] ?? 'Sample Customer Ltd'),
                (string) ($payload['project'] ?? 'Sample project'),
                'office',
            );
        } else {
            $answer = $answerer->answer($question);
        }

        return new JsonResponse([
            'text' => $answer->text,
            'sources' => $answer->sources,
            'warnings' => $answer->warnings,
            'draft_id' => $answer->draftId,
            'declined' => $answer->declined,
        ]);
    }
}

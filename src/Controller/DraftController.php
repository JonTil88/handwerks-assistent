<?php

declare(strict_types=1);

namespace Craft\Assistant\Controller;

use Craft\Assistant\Entity\AuditEntry;
use Craft\Assistant\Repository\QuotationDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DraftController extends AbstractController
{
    #[Route('/draft/{id}', name: 'app_draft', methods: ['GET'])]
    public function show(int $id, QuotationDraftRepository $drafts): Response
    {
        $draft = $drafts->find($id);

        if (null === $draft) {
            throw $this->createNotFoundException('Quotation draft not found.');
        }

        return $this->render('draft/show.html.twig', [
            'draft' => $draft,
        ]);
    }

    #[Route('/draft/{id}/approve', name: 'app_draft_approve', methods: ['POST'])]
    public function approve(
        int $id,
        Request $request,
        QuotationDraftRepository $drafts,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        // Approval is a human act — it must not be triggerable from another page.
        if (!$this->isCsrfTokenValid('approve', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $draft = $drafts->find($id);

        if (null === $draft) {
            throw $this->createNotFoundException('Quotation draft not found.');
        }

        $person = trim((string) $request->request->get('person', 'office'));
        $draft->approve('' === $person ? 'office' : $person);

        $entityManager->persist(new AuditEntry(
            'quotation_draft_approved',
            $draft->approvedBy() ?? 'office',
            'quotation_draft:'.$draft->number(),
            [
                'grand_total' => $draft->grandTotal(),
                'lines' => $draft->lines()->count(),
            ],
        ));
        $entityManager->flush();

        $this->addFlash('status', sprintf('Draft %s was approved.', $draft->number()));

        return $this->redirectToRoute('app_draft', ['id' => $id]);
    }
}

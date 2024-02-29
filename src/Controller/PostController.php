<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class PostController extends AbstractController
{
    #[Route('/posts', name: 'app_post')]
    public function index(
        PostRepository      $postRepository,
        SerializerInterface $serializer,
        Request             $request,
        SessionRepository   $sessionRepository
    ): JsonResponse
    {
        $sessionCookie = str_replace("sessionId=", "", $request->headers->get("cookie"));

        if (!$sessionCookie) {
//            $jsonResponse = new JsonResponse('accès refusé', 401);
//            $jsonResponse->headers->clearCookie('sessionId', '/', null, true, true);
            return new JsonResponse('accès refusé', 401);
        }

        $session = $sessionRepository->findOneBy(["session" => $sessionCookie]);

        if (!$session) {
            return new JsonResponse('accès refusé', 401);
        }

        $posts = $postRepository->findAll();

        $jsonPosts = $serializer->serialize($posts, "json", ['groups' => "post:read"]);

        return new JsonResponse($jsonPosts, 200, [], true);
    }
}

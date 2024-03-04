<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class PostController extends AbstractController
{
    #[Route('/posts', name: 'app_post', methods: ["GET"])]
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

        $posts = $postRepository->findBy([], ['createdAt' => "DESC"]);

        $jsonPosts = $serializer->serialize($posts, "json", ['groups' => "post:read"]);

        return new JsonResponse($jsonPosts, 200, [], true);
    }

    #[Route("/posts", methods: ['POST'])]
    public function createPost(
        PostRepository         $postRepository,
        SerializerInterface    $serializer,
        Request                $request,
        SessionRepository      $sessionRepository,
        EntityManagerInterface $em
    )
    {
        $sessionCookie = str_replace("sessionId=", "", $request->headers->get("cookie"));

        if (!$sessionCookie) {

            return new JsonResponse('accès refusé', 401);
        }

        $session = $sessionRepository->findOneBy(["session" => $sessionCookie]);

        if (!$session) {
            return new JsonResponse('accès refusé', 401);
        }

        $user = $session->getUser();

        $content = $request->getContent();

        $post = $serializer->deserialize($content, Post::class, 'json');
        $post->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($post);
        $em->flush();

        return $this->json($post, Response::HTTP_CREATED, [], ["groups" => "post:read"]);
    }

    #[Route("/posts/{id}", methods: ['DELETE'])]
    public function deletePost(
        Post                   $post,
        PostRepository         $postRepository,
        SerializerInterface    $serializer,
        Request                $request,
        SessionRepository      $sessionRepository,
        EntityManagerInterface $em
    )
    {
        $sessionCookie = str_replace("sessionId=", "", $request->headers->get("cookie"));

        if (!$sessionCookie) {

            return new JsonResponse('accès refusé', 401);
        }

        $session = $sessionRepository->findOneBy(["session" => $sessionCookie]);

        if (!$session) {
            return new JsonResponse('accès refusé', 401);
        }

        $user = $session->getUser();

        if ($user !== $post->getUser()) {
            return $this->json(["message" => "Accès refusé"], Response::HTTP_FORBIDDEN);
        }

        $em->remove($post);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

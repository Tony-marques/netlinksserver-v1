<?php

namespace App\Controller;

use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class UserController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(
        Request                     $request,
        UserPasswordHasherInterface $hasher,
        UserRepository              $userRepository,
        EntityManagerInterface      $em,
    ): JsonResponse
    {
        $content = $request->toArray();
        $user = $userRepository->findOneBy(["email" => $content["email"]]);

        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 50; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        if ($user) {
            if ($hasher->isPasswordValid($user, $content["password"])) {
                $session = new Session();
                $session->setSession($randomString);

                $user->setSession($session);

                $em->persist($user);
                $em->flush();

                $cookie = new Cookie("sessionId", $randomString, strtotime('+1 day'), sameSite: "none");

                $jsonResponse = new JsonResponse(['message' => 'Connexion réussie']);

                $jsonResponse->headers->setCookie($cookie);

                return $jsonResponse;
            }

            return new JsonResponse("Les informations sont incorrects", Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse("Les informations sont incorrects", Response::HTTP_UNAUTHORIZED);
    }

    #[Route(path: "/me", name: "me")]
    public function me(UserRepository $userRepository, SessionRepository $sessionRepository, Request $request)
    {
        $sessionCookie = str_replace("sessionId=", "", $request->headers->get("cookie"));

        if ($sessionCookie) {
            $session = $sessionRepository->findOneBy(["session" => $sessionCookie]);

            $user = $session->getUser();

            return $this->json($user, 200, [], ["groups" => "user:read"]);
        }

        return new JsonResponse("Les informations sont incorrects", Response::HTTP_UNAUTHORIZED);
    }

    #[Route(path: "/signup", name: "signup")]
    public function signup(
        UserRepository              $userRepository,
        Request                     $request,
        SerializerInterface         $serializer,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $hasher
    )
    {
        $user = $serializer->deserialize($request->getContent(), User::class, "json");
        $user->setPassword($hasher->hashPassword($user, $user->getPassword()))
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->json(["message" => "L'utilisateur a bien été créé!"], Response::HTTP_CREATED);
    }
}

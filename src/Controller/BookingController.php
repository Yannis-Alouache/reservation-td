<?php 

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Service;
use App\Entity\User;
use App\Form\BookingFirstStepType;
use App\Form\BookingSecondStepType;
use App\Repository\BookingRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


class BookingController extends AbstractController
{

    #[Route('/', name: 'app_booking_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        return $this->render('booking/index.html.twig', [
            'services' => $serviceRepository->findAllOrderedByName(),
        ]);
    }
    
    #[Route('/booking/new/step-1', name: 'app_booking_step1', methods: ['GET', 'POST'])]
    public function step1(
        Request $request, 
        SessionInterface $session,
        BookingRepository $bookingRepository
    ): Response {
        $booking = new Booking();
    
        $form = $this->createForm(BookingFirstStepType::class, $booking);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $overlappingBookings = $bookingRepository->findOverlappingBookings(
                $booking->getStartTime(),
                $booking->getService()->getDuration()
            );
            
            if (count($overlappingBookings) > 0) {
                $this->addFlash('error', 'Ce créneau horaire est déjà réservé. Veuillez choisir un autre moment.');
                return $this->render('booking/step-1.html.twig', [
                    'form' => $form,
                ]);
            }
    
            $session->set('booking', [
                'serviceId' => $booking->getService()->getId(),
                'startTime' => $booking->getStartTime()
            ]);
    
            return $this->redirectToRoute('app_booking_step2');
        }
    
        return $this->render('booking/step-1.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/booking/new/step-2', name: 'app_booking_step2', methods: ['GET', 'POST'])]
    public function step2(
        Request $request, 
        SessionInterface $session, 
        EntityManagerInterface $entityManager
    ): Response
    {
        if (!$session->has('booking')) {
            return $this->redirectToRoute('app_booking_step1');
        }

        $booking = new Booking();
        $bookingData = $session->get('booking');
        
        $service = $entityManager->getRepository(Service::class)->find($bookingData['serviceId']);
        
        $booking->setService($service);
        $booking->setStartTime($bookingData['startTime']);

        $form = $this->createForm(BookingSecondStepType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userEmail = $form->get('userEmail')->getData();
            
            $userRepository = $entityManager->getRepository(User::class);
            $user = $userRepository->findOneBy(['email' => $userEmail]);
        
            if (!$user) {
                $this->addFlash('error', 'Cette adresse email n\'est pas associée à un compte utilisateur.');
                return $this->render('booking/step-2.html.twig', [
                    'form' => $form,
                    'booking' => $booking,
                ]);
            }
        
            $booking->setUser($user);
            
            $entityManager->persist($booking);
            $entityManager->flush();
        
            $session->remove('booking');
        
            return $this->redirectToRoute('app_booking_success');
        }

        return $this->render('booking/step-2.html.twig', [
            'form' => $form,
            'booking' => $booking,
        ]);
    }

    #[Route('/booking/manage', name: 'app_booking_manage', methods: ['GET', 'POST'])]
    public function manageBookings(
        Request $request, 
        BookingRepository $bookingRepository,
        EntityManagerInterface $em

    ): Response {
        $email = $request->query->get('email');
        
        if ($email) {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $bookings = $bookingRepository->findUpcomingBookingsByUser($user);
                return $this->render('booking/manage.html.twig', [
                    'bookings' => $bookings,
                    'email' => $email
                ]);
            }
        }
        
        return $this->render('booking/search.html.twig');
    }


    #[Route('/booking/{id}/cancel/{email}', name: 'app_booking_cancel', methods: ['POST'])]
    public function cancel(
        Booking $booking,
        string $email,
        EntityManagerInterface $em
    ): Response {
        if ($booking->getUser()->getEmail() == $email) {
            $em->remove($booking);
            $em->flush();    
        }

        return $this->redirectToRoute('app_booking_manage', ['email' => $email]);
    }


    #[Route('/booking/success', name: 'app_booking_success')]
    public function success(): Response
    {
        return $this->render('booking/success.html.twig');
    }
}
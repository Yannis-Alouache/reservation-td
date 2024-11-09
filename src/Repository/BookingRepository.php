<?php

namespace App\Repository;

use DateInterval;
use App\Entity\User;
use DateTimeInterface;
use App\Entity\Booking;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findOverlappingBookings(DateTimeInterface $startTime, int $duration): array
    {
        $endTime = $startTime;
        $endTime->add(new DateInterval('PT' . $duration . 'M'));
        
        $qb = $this->createQueryBuilder('b')
            ->join('b.service', 's')
            ->andWhere('b.startTime < :endTime')
            ->andWhere('DATE_ADD(b.startTime, s.duration, \'minute\') > :startTime')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);
        
        return $qb->getQuery()->getResult();
    }

    public function findUpcomingBookingsByUser(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.startTime > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('b.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Booking[] Returns an array of Booking objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Booking
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

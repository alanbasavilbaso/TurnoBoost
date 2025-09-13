-- validateSlotAvailability
select  count(a.id) as appointments , 
		CASE
            WHEN ss.id is not null and sss.special_schedule_id is not null THEN 1
            ELSE 0
        END AS has_special_schedules,
        pa.id as professionalAvailability, 
        pb.id as blocks
from professionals p 
left join professional_availability pa
on p.id=pa.professional_id and pa.weekday=4
join professional_services ps 
on p.id=ps.professional_id and p.id=2 
left join appointments a
on p.id=a.professional_id and a.status<> 'cancelled' and DATE(a.scheduled_at) = '2025-09-12' and a.scheduled_at::TIME BETWEEN '10:30:00'::TIME AND '11:00:00'::TIME
left join special_schedules ss
on ss.professional_id=p.id and ss.date = '2025-09-12' and ss.start_time::TIME <= '10:30:00'::TIME and ss.end_time::TIME >= '11:00:00'::TIME 
left join special_schedule_services sss
on sss.special_schedule_id = ss.id and sss.service_id=1 and ss.id is not null
LEFT JOIN professional_blocks pb
on pb.professional_id=p.id and pb.start_date >= '2025-09-12' and (pb.end_date is null or pb.end_date >= '2025-09-12') and ((pb.start_time is null and pb.end_time is null) or (pb.start_time between '10:30:00'::TIME AND '11:00:00'::TIME and pb.end_time between '10:30:00'::TIME AND '11:00:00'::TIME))

where 1=1

and p.id = 2
and ps.service_id = 1
and (pa.weekday=4 or ss.id is not null)
and ps.available_tuesday=true
group by a.id, ss.id, sss.special_schedule_id, pa.id, pb.id






-- ver horarios disponibles
-- Horarios regulares
SELECT 
    pa.start_time AS start_time,
    pa.end_time AS end_time,
    'Disponibilidad Regular' AS tipo
FROM professional_availability pa
WHERE pa.professional_id = 2
    AND pa.weekday = 3

UNION ALL


SELECT 
    ss.start_time AS start_time,
    ss.end_time AS end_time, 
    'Horario Especial' AS tipo
FROM special_schedules ss
WHERE ss.professional_id = 2
    AND EXTRACT(DOW FROM ss.date) = 3     AND ss.date = '2025-09-09' 
ORDER BY start_time; 
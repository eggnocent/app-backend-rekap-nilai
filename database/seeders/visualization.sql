BEGIN;

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), lecturers AS (
    SELECT * FROM (VALUES
        ('demo.lecturer01@nilaiku.demo', 'Dr. Rendra Saputra, M.Kom.', '0900000001'),
        ('demo.lecturer02@nilaiku.demo', 'Nadia Kurniawati, M.T.', '0900000002'),
        ('demo.lecturer03@nilaiku.demo', 'Fajar Pratama, M.Kom.', '0900000003'),
        ('demo.lecturer04@nilaiku.demo', 'Dr. Intan Permata, M.Cs.', '0900000004'),
        ('demo.lecturer05@nilaiku.demo', 'Rizky Maulana, M.T.', '0900000005'),
        ('demo.lecturer06@nilaiku.demo', 'Salsa Anindita, M.Kom.', '0900000006'),
        ('demo.lecturer07@nilaiku.demo', 'Bagas Nugroho, M.Cs.', '0900000007'),
        ('demo.lecturer08@nilaiku.demo', 'Diah Lestari, M.T.', '0900000008')
    ) AS value(email, name, nidn)
)
INSERT INTO users (name, email, password_hash, role, identifier, phone, is_active, created_by)
SELECT lecturers.name, lecturers.email, crypt('Demo12345!', gen_salt('bf', 12)), 'lecturer', lecturers.nidn, '0813000000' || right(lecturers.nidn, 2), TRUE, actor.id
FROM lecturers
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = lecturers.email);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
)
INSERT INTO lecturer_profiles (user_id, nidn, faculty, major, created_by)
SELECT users.id, users.identifier, 'Fakultas Ilmu Komputer', 'Informatika', actor.id
FROM users
CROSS JOIN actor
WHERE users.email LIKE 'demo.lecturer%@nilaiku.demo'
  AND NOT EXISTS (SELECT 1 FROM lecturer_profiles WHERE user_id = users.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), students AS (
    SELECT * FROM (VALUES
        (1, 'Alya Putri Maheswari'), (2, 'Bima Aditya Pratama'), (3, 'Citra Lestari'), (4, 'Dimas Arya Wibowo'),
        (5, 'Eka Nuraini'), (6, 'Farhan Ramadhan'), (7, 'Gita Anindya'), (8, 'Hafiz Maulana'),
        (9, 'Indah Permata Sari'), (10, 'Joko Prasetyo'), (11, 'Karina Oktaviani'), (12, 'Lukman Hakim'),
        (13, 'Maya Salsabila'), (14, 'Naufal Rizki'), (15, 'Olivia Maharani'), (16, 'Putra Kurniawan'),
        (17, 'Qori Azzahra'), (18, 'Rafi Akbar'), (19, 'Sinta Maharani'), (20, 'Taufik Hidayat'),
        (21, 'Ulfa Khairunnisa'), (22, 'Vino Pradana'), (23, 'Wulan Anggraini'), (24, 'Yoga Ramadhan')
    ) AS value(sequence, name)
)
INSERT INTO users (name, email, password_hash, role, identifier, phone, is_active, created_by)
SELECT students.name, 'demo.student' || lpad(students.sequence::text, 2, '0') || '@nilaiku.demo', crypt('Demo12345!', gen_salt('bf', 12)), 'student', '25.11.' || lpad(students.sequence::text, 4, '0'), '0814000000' || lpad(students.sequence::text, 2, '0'), TRUE, actor.id
FROM students
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'demo.student' || lpad(students.sequence::text, 2, '0') || '@nilaiku.demo');

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
)
INSERT INTO student_profiles (user_id, nim, faculty, major, entry_year, current_semester, total_credits_target, status, created_by)
SELECT users.id, users.identifier, 'Fakultas Ilmu Komputer', 'Informatika', 2023, 6, 144, 'active', actor.id
FROM users
CROSS JOIN actor
WHERE users.email LIKE 'demo.student%@nilaiku.demo'
  AND NOT EXISTS (SELECT 1 FROM student_profiles WHERE user_id = users.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), terms AS (
    SELECT * FROM (VALUES
        ('2022/2023 Ganjil', '2022/2023', 'Ganjil', DATE '2022-08-22', DATE '2023-01-20'),
        ('2022/2023 Genap', '2022/2023', 'Genap', DATE '2023-02-13', DATE '2023-06-30'),
        ('2023/2024 Ganjil', '2023/2024', 'Ganjil', DATE '2023-08-21', DATE '2024-01-19'),
        ('2023/2024 Genap', '2023/2024', 'Genap', DATE '2024-02-12', DATE '2024-06-28'),
        ('2024/2025 Ganjil', '2024/2025', 'Ganjil', DATE '2024-08-19', DATE '2025-01-17'),
        ('2024/2025 Genap', '2024/2025', 'Genap', DATE '2025-02-10', DATE '2025-06-27')
    ) AS value(name, academic_year, semester, start_date, end_date)
)
INSERT INTO academic_terms (name, academic_year, semester, start_date, end_date, is_active, created_by)
SELECT terms.name, terms.academic_year, terms.semester, terms.start_date, terms.end_date, FALSE, actor.id
FROM terms
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM academic_terms WHERE name = terms.name);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), seed_courses AS (
    SELECT * FROM (VALUES
        (1, 'VIS101', 'Algoritma dan Pemrograman', 3, 1),
        (2, 'VIS102', 'Basis Data', 3, 2),
        (3, 'VIS103', 'Pemrograman Web', 3, 3),
        (4, 'VIS104', 'Interaksi Manusia dan Komputer', 3, 4),
        (5, 'VIS105', 'Analisis dan Perancangan Sistem', 3, 4),
        (6, 'VIS106', 'Pengujian Perangkat Lunak', 3, 5),
        (7, 'VIS107', 'Manajemen Proyek TI', 3, 5),
        (8, 'VIS108', 'Data Mining', 3, 6)
    ) AS value(sequence, code, name, credits, recommended_semester)
), lecturers AS (
    SELECT lecturer_profiles.id, row_number() OVER (ORDER BY users.email) AS sequence
    FROM lecturer_profiles
    INNER JOIN users ON users.id = lecturer_profiles.user_id
    WHERE users.email LIKE 'demo.lecturer%@nilaiku.demo'
)
INSERT INTO courses (code, name, credits, recommended_semester, lecturer_id, status, created_by)
SELECT seed_courses.code, seed_courses.name, seed_courses.credits, seed_courses.recommended_semester, lecturers.id, 'Aktif', actor.id
FROM seed_courses
INNER JOIN lecturers ON lecturers.sequence = ((seed_courses.sequence - 1) % 8) + 1
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.courses existing WHERE existing.code = seed_courses.code);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), terms AS (
    SELECT id, academic_year, semester, row_number() OVER (ORDER BY start_date) AS sequence
    FROM academic_terms
    WHERE name IN ('2022/2023 Ganjil', '2022/2023 Genap', '2023/2024 Ganjil', '2023/2024 Genap', '2024/2025 Ganjil', '2024/2025 Genap')
), seed_courses AS (
    SELECT id, code, row_number() OVER (ORDER BY code) AS sequence
    FROM courses
    WHERE code LIKE 'VIS%'
), lecturers AS (
    SELECT lecturer_profiles.id, row_number() OVER (ORDER BY users.email) AS sequence
    FROM lecturer_profiles
    INNER JOIN users ON users.id = lecturer_profiles.user_id
    WHERE users.email LIKE 'demo.lecturer%@nilaiku.demo'
)
INSERT INTO classes (term_id, course_id, lecturer_id, code, capacity, status, created_by)
SELECT terms.id, seed_courses.id, lecturers.id, seed_courses.code || '-' || replace(terms.academic_year, '/', '') || left(terms.semester, 1) || '-A', 40, 'Ditutup', actor.id
FROM terms
CROSS JOIN seed_courses
INNER JOIN lecturers ON lecturers.sequence = ((terms.sequence + seed_courses.sequence - 2) % 8) + 1
CROSS JOIN actor
WHERE NOT EXISTS (
    SELECT 1 FROM public.classes existing
    WHERE existing.term_id = terms.id AND existing.course_id = seed_courses.id
);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), active_term AS (
    SELECT id FROM academic_terms WHERE is_active IS TRUE ORDER BY start_date DESC NULLS LAST LIMIT 1
), seed_courses AS (
    SELECT id, code, row_number() OVER (ORDER BY code) AS sequence
    FROM courses
    WHERE code LIKE 'VIS%'
), lecturers AS (
    SELECT lecturer_profiles.id, row_number() OVER (ORDER BY users.email) AS sequence
    FROM lecturer_profiles
    INNER JOIN users ON users.id = lecturer_profiles.user_id
    WHERE users.email LIKE 'demo.lecturer%@nilaiku.demo'
)
INSERT INTO classes (term_id, course_id, lecturer_id, code, capacity, status, created_by)
SELECT active_term.id, seed_courses.id, lecturers.id, seed_courses.code || '-A', 40, 'Aktif', actor.id
FROM active_term
CROSS JOIN seed_courses
INNER JOIN lecturers ON lecturers.sequence = seed_courses.sequence
CROSS JOIN actor
WHERE NOT EXISTS (
    SELECT 1 FROM public.classes existing
    WHERE existing.term_id = active_term.id AND existing.course_id = seed_courses.id
);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), seed_classes AS (
    SELECT classes.id, row_number() OVER (ORDER BY classes.code) AS sequence
    FROM classes
    INNER JOIN courses ON courses.id = classes.course_id
    WHERE courses.code LIKE 'VIS%'
)
INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, room, created_by)
SELECT seed_classes.id,
       ((seed_classes.sequence - 1) % 6) + 1,
       CASE ((seed_classes.sequence - 1) % 3) WHEN 0 THEN TIME '08:00' WHEN 1 THEN TIME '10:30' ELSE TIME '13:00' END,
       CASE ((seed_classes.sequence - 1) % 3) WHEN 0 THEN TIME '09:40' WHEN 1 THEN TIME '12:10' ELSE TIME '14:40' END,
       'R. ' || (((seed_classes.sequence - 1) % 4) + 1) || '.' || (((seed_classes.sequence - 1) % 6) + 1) || '.0' || ((seed_classes.sequence - 1) % 5 + 1),
       actor.id
FROM seed_classes
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.class_schedules WHERE class_id = seed_classes.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), students AS (
    SELECT student_profiles.id, row_number() OVER (ORDER BY users.email) AS sequence
    FROM student_profiles
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE users.email LIKE 'demo.student%@nilaiku.demo'
), seed_classes AS (
    SELECT classes.id, classes.term_id, row_number() OVER (PARTITION BY classes.term_id ORDER BY classes.code) AS sequence
    FROM classes
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN academic_terms ON academic_terms.id = classes.term_id
    WHERE courses.code LIKE 'VIS%' AND academic_terms.is_active IS FALSE
)
INSERT INTO enrollments (student_id, class_id, status, enrolled_at, created_by)
SELECT students.id, seed_classes.id, 'Terdaftar', NOW() - INTERVAL '18 months', actor.id
FROM students
INNER JOIN seed_classes ON ((students.sequence + seed_classes.sequence) % 6) < 4
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.enrollments existing WHERE existing.student_id = students.id AND existing.class_id = seed_classes.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), students AS (
    SELECT student_profiles.id, row_number() OVER (ORDER BY users.email) AS sequence
    FROM student_profiles
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE users.email LIKE 'demo.student%@nilaiku.demo'
), active_term AS (
    SELECT id FROM academic_terms WHERE is_active IS TRUE ORDER BY start_date DESC NULLS LAST LIMIT 1
), seed_classes AS (
    SELECT classes.id, row_number() OVER (ORDER BY classes.code) AS sequence
    FROM classes
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN active_term ON active_term.id = classes.term_id
    WHERE courses.code LIKE 'VIS%'
)
INSERT INTO enrollments (student_id, class_id, status, enrolled_at, created_by)
SELECT students.id, seed_classes.id, 'Terdaftar', NOW() - INTERVAL '90 days', actor.id
FROM students
INNER JOIN seed_classes ON ((students.sequence + seed_classes.sequence) % 8) < 6
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.enrollments existing WHERE existing.student_id = students.id AND existing.class_id = seed_classes.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), rows AS (
    SELECT enrollments.id, classes.id AS class_id, student_profiles.id AS student_id, academic_terms.end_date,
           row_number() OVER (ORDER BY academic_terms.start_date, classes.code, student_profiles.nim) AS sequence
    FROM enrollments
    INNER JOIN classes ON classes.id = enrollments.class_id
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN academic_terms ON academic_terms.id = classes.term_id
    INNER JOIN student_profiles ON student_profiles.id = enrollments.student_id
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE courses.code LIKE 'VIS%'
      AND academic_terms.is_active IS FALSE
      AND users.email LIKE 'demo.student%@nilaiku.demo'
), scores AS (
    SELECT rows.*, (70 + (rows.sequence % 21))::numeric AS daily, (78 + (rows.sequence % 21))::numeric AS attendance,
           (68 + (rows.sequence % 25))::numeric AS midterm, (70 + (rows.sequence % 24))::numeric AS final_exam
    FROM rows
)
INSERT INTO grades (enrollment_id, daily_score, attendance_score, midterm_score, final_exam_score, final_score, letter_grade, status, submitted_at, verified_at, published_at, created_by, updated_by)
SELECT scores.id, scores.daily, scores.attendance, scores.midterm, scores.final_exam,
       ROUND(scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40, 2),
       CASE WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 80 THEN 'A'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 70 THEN 'B'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 60 THEN 'C'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 50 THEN 'D' ELSE 'E' END,
       'published', scores.end_date - INTERVAL '14 days', scores.end_date - INTERVAL '10 days', scores.end_date - INTERVAL '7 days', actor.id, actor.id
FROM scores
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.grades WHERE enrollment_id = scores.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), active_term AS (
    SELECT id, start_date FROM academic_terms WHERE is_active IS TRUE ORDER BY start_date DESC NULLS LAST LIMIT 1
), seed_classes AS (
    SELECT classes.id, row_number() OVER (ORDER BY classes.code) AS sequence
    FROM classes
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN active_term ON active_term.id = classes.term_id
    WHERE courses.code LIKE 'VIS%'
)
INSERT INTO attendance_meetings (class_id, meeting_date, topic, created_by)
SELECT seed_classes.id, active_term.start_date + ((series.sequence * 7) + 7), 'Pertemuan ' || series.sequence || ' · Materi pembelajaran dan studi kasus', actor.id
FROM seed_classes
CROSS JOIN active_term
CROSS JOIN actor
CROSS JOIN generate_series(1, 10) AS series(sequence)
WHERE NOT EXISTS (
    SELECT 1 FROM attendance_meetings existing
    WHERE existing.class_id = seed_classes.id AND existing.meeting_date = active_term.start_date + ((series.sequence * 7) + 7)
);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), rows AS (
    SELECT attendance_meetings.id AS meeting_id, enrollments.id AS enrollment_id, attendance_meetings.meeting_date,
           row_number() OVER (ORDER BY attendance_meetings.id, enrollments.id) AS sequence
    FROM attendance_meetings
    INNER JOIN classes ON classes.id = attendance_meetings.class_id
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN enrollments ON enrollments.class_id = classes.id AND enrollments.status = 'Terdaftar'
    INNER JOIN student_profiles ON student_profiles.id = enrollments.student_id
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE courses.code LIKE 'VIS%' AND users.email LIKE 'demo.student%@nilaiku.demo'
)
INSERT INTO attendance_records (meeting_id, enrollment_id, status, recorded_at, created_by)
SELECT rows.meeting_id, rows.enrollment_id,
       CASE WHEN rows.sequence % 17 = 0 THEN 'Alpha' WHEN rows.sequence % 11 = 0 THEN 'Izin' WHEN rows.sequence % 7 = 0 THEN 'Terlambat' ELSE 'Hadir' END,
       rows.meeting_date + TIME '10:00', actor.id
FROM rows
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM attendance_records WHERE meeting_id = rows.meeting_id AND enrollment_id = rows.enrollment_id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), rows AS (
    SELECT attendance_meetings.id AS meeting_id, enrollments.id AS enrollment_id,
           row_number() OVER (ORDER BY attendance_meetings.id, enrollments.id) AS sequence
    FROM attendance_meetings
    INNER JOIN classes ON classes.id = attendance_meetings.class_id
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN enrollments ON enrollments.class_id = classes.id AND enrollments.status = 'Terdaftar'
    INNER JOIN student_profiles ON student_profiles.id = enrollments.student_id
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE courses.code LIKE 'VIS%' AND users.email LIKE 'demo.student%@nilaiku.demo'
)
INSERT INTO meeting_scores (meeting_id, enrollment_id, score, created_by)
SELECT rows.meeting_id, rows.enrollment_id, 70 + (rows.sequence % 26), actor.id
FROM rows
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM meeting_scores WHERE meeting_id = rows.meeting_id AND enrollment_id = rows.enrollment_id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), rows AS (
    SELECT enrollments.id, row_number() OVER (ORDER BY classes.code, student_profiles.nim) AS sequence
    FROM enrollments
    INNER JOIN classes ON classes.id = enrollments.class_id
    INNER JOIN courses ON courses.id = classes.course_id
    INNER JOIN academic_terms ON academic_terms.id = classes.term_id
    INNER JOIN student_profiles ON student_profiles.id = enrollments.student_id
    INNER JOIN users ON users.id = student_profiles.user_id
    WHERE courses.code LIKE 'VIS%'
      AND academic_terms.is_active IS TRUE
      AND users.email LIKE 'demo.student%@nilaiku.demo'
), scores AS (
    SELECT rows.id, rows.sequence, (72 + (rows.sequence % 20))::numeric AS daily, (75 + (rows.sequence % 21))::numeric AS attendance,
           (66 + (rows.sequence % 27))::numeric AS midterm, (68 + (rows.sequence % 27))::numeric AS final_exam
    FROM rows
)
INSERT INTO grades (enrollment_id, daily_score, attendance_score, midterm_score, final_exam_score, final_score, letter_grade, status, submitted_at, verified_at, published_at, created_by, updated_by)
SELECT scores.id, scores.daily, scores.attendance, scores.midterm, scores.final_exam,
       ROUND(scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40, 2),
       CASE WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 80 THEN 'A'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 70 THEN 'B'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 60 THEN 'C'
            WHEN scores.daily * 0.20 + scores.attendance * 0.10 + scores.midterm * 0.30 + scores.final_exam * 0.40 >= 50 THEN 'D' ELSE 'E' END,
       CASE scores.sequence % 5 WHEN 0 THEN 'published' WHEN 1 THEN 'draft' WHEN 2 THEN 'submitted' WHEN 3 THEN 'verified' ELSE 'returned' END,
       CASE WHEN scores.sequence % 5 IN (0, 2, 3, 4) THEN NOW() - INTERVAL '12 days' ELSE NULL END,
       CASE WHEN scores.sequence % 5 IN (0, 3) THEN NOW() - INTERVAL '7 days' ELSE NULL END,
       CASE WHEN scores.sequence % 5 = 0 THEN NOW() - INTERVAL '3 days' ELSE NULL END,
       actor.id, actor.id
FROM scores
CROSS JOIN actor
WHERE NOT EXISTS (SELECT 1 FROM public.grades WHERE enrollment_id = scores.id);

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), seed_grades AS (
    SELECT grades.id, grades.status, grades.created_at, grades.submitted_at, grades.verified_at, grades.published_at
    FROM grades
    INNER JOIN enrollments ON enrollments.id = grades.enrollment_id
    INNER JOIN classes ON classes.id = enrollments.class_id
    INNER JOIN courses ON courses.id = classes.course_id
    WHERE courses.code LIKE 'VIS%'
)
INSERT INTO grade_status_history (grade_id, from_status, to_status, note, created_at, created_by)
SELECT seed_grades.id, transitions.from_status, transitions.to_status, transitions.note, transitions.created_at, actor.id
FROM seed_grades
CROSS JOIN actor
CROSS JOIN LATERAL (
    SELECT NULL::varchar AS from_status, 'draft'::varchar AS to_status, NULL::text AS note, seed_grades.created_at AS created_at
    UNION ALL SELECT 'draft', 'submitted', NULL, seed_grades.submitted_at WHERE seed_grades.status IN ('submitted', 'verified', 'published', 'returned')
    UNION ALL SELECT 'submitted', 'verified', NULL, seed_grades.verified_at WHERE seed_grades.status IN ('verified', 'published')
    UNION ALL SELECT 'verified', 'published', NULL, seed_grades.published_at WHERE seed_grades.status = 'published'
    UNION ALL SELECT 'submitted', 'returned', 'Mohon lengkapi bukti penilaian dan perbarui komponen nilai.', seed_grades.submitted_at + INTERVAL '2 days' WHERE seed_grades.status = 'returned'
) AS transitions
WHERE transitions.created_at IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM grade_status_history existing
      WHERE existing.grade_id = seed_grades.id AND existing.to_status = transitions.to_status
  );

WITH actor AS (
    SELECT id FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), active_term AS (
    SELECT id FROM academic_terms WHERE is_active IS TRUE ORDER BY start_date DESC NULLS LAST LIMIT 1
), events AS (
    SELECT * FROM (VALUES
        ('Batas Akhir Pengisian Nilai', 'Dosen diharapkan menyelesaikan pengisian nilai sebelum batas waktu.', NOW() + INTERVAL '3 days', NOW() + INTERVAL '3 days 8 hours', 'Sistem NilaiKu', 'lecturer'),
        ('Workshop Persiapan UTS', 'Sesi penguatan materi dan strategi belajar menjelang ujian tengah semester.', NOW() + INTERVAL '6 days', NOW() + INTERVAL '6 days 3 hours', 'Aula Fakultas', 'student'),
        ('Rapat Evaluasi Akademik', 'Koordinasi evaluasi proses pembelajaran semester berjalan.', NOW() + INTERVAL '9 days', NOW() + INTERVAL '9 days 2 hours', 'Ruang Rapat FIK', 'lecturer'),
        ('Pengumuman Jadwal UAS', 'Jadwal ujian akhir semester telah tersedia untuk seluruh civitas akademika.', NOW() + INTERVAL '12 days', NOW() + INTERVAL '12 days 2 hours', 'Portal Akademik', 'all'),
        ('Konsultasi KRS Semester Berikutnya', 'Mahasiswa dapat berkonsultasi dengan dosen wali mengenai rencana studi.', NOW() + INTERVAL '16 days', NOW() + INTERVAL '16 days 5 hours', 'Ruang Dosen', 'student'),
        ('Pemeliharaan Sistem Akademik', 'Layanan akademik dapat mengalami gangguan singkat selama pemeliharaan.', NOW() + INTERVAL '20 days', NOW() + INTERVAL '20 days 2 hours', 'Sistem NilaiKu', 'all')
    ) AS value(title, description, starts_at, ends_at, location, audience)
)
INSERT INTO academic_events (term_id, title, description, starts_at, ends_at, location, audience, created_by)
SELECT active_term.id, events.title, events.description, events.starts_at, events.ends_at, events.location, events.audience, actor.id
FROM active_term
CROSS JOIN actor
CROSS JOIN events
WHERE NOT EXISTS (SELECT 1 FROM academic_events WHERE term_id = active_term.id AND title = events.title);

WITH actor AS (
    SELECT id, email FROM users WHERE role = 'admin' ORDER BY created_at LIMIT 1
), activities AS (
    SELECT * FROM (VALUES
        (80::bigint, 'visual_seed_academic', 'Menyiapkan semester, kelas, dan jadwal visualisasi.'),
        (40::bigint, 'visual_seed_enrollment', 'Mengisi KRS mahasiswa untuk visualisasi dashboard.'),
        (50::bigint, 'visual_seed_grade', 'Melengkapi workflow nilai dan transkrip mahasiswa.'),
        (60::bigint, 'visual_seed_attendance', 'Membuat pertemuan dan rekap presensi kelas.'),
        (70::bigint, 'visual_seed_event', 'Menambahkan agenda akademik semester berjalan.')
    ) AS value(activity_type, activity, activity_string)
)
INSERT INTO activity_log (user_id, activity_type, activity, activity_string, role, email, created_by)
SELECT actor.id, activities.activity_type, activities.activity, activities.activity_string, 'admin', actor.email, actor.id
FROM actor
CROSS JOIN activities
WHERE NOT EXISTS (SELECT 1 FROM activity_log WHERE activity = activities.activity);

COMMIT;

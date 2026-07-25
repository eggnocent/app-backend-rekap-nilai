-- Penilaian per pertemuan (lihat app-frontend/docs/blueprint-penilaian-per-pertemuan.md).
-- Nilai formatif tunggal (assignment_score) menjadi rata-rata skor per pertemuan
-- (daily_score), ditambah komponen kehadiran (attendance_score).

ALTER TABLE grades RENAME COLUMN assignment_score TO daily_score;
ALTER TABLE grades ADD COLUMN IF NOT EXISTS attendance_score NUMERIC(5, 2);

-- Skor per pertemuan, satu baris per (pertemuan, mahasiswa). Pertemuan diambil
-- dari attendance_meetings; diinput terpisah dari status kehadiran.
CREATE TABLE IF NOT EXISTS meeting_scores (
    id            UUID        DEFAULT gen_random_uuid() NOT NULL,
    meeting_id    UUID,
    enrollment_id UUID,
    score         NUMERIC(5, 2),
    created_at    TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by    UUID                                  NOT NULL,
    updated_at    TIMESTAMPTZ,
    updated_by    UUID,
    PRIMARY KEY (id),
    UNIQUE (meeting_id, enrollment_id),
    FOREIGN KEY (meeting_id) REFERENCES attendance_meetings (id),
    FOREIGN KEY (enrollment_id) REFERENCES enrollments (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);

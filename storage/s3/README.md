# S3 Storage

Folder ini disiapkan untuk integrasi upload ke Amazon S3 atau layanan yang kompatibel dengan S3.

File unggahan tidak disimpan di server aplikasi. Nantinya modul ini berisi kode untuk membuat object key, mengunggah file, mengambil URL, dan menghapus object dari bucket.

Konfigurasi bucket, region, endpoint, access key, dan secret key harus disimpan sebagai environment variable, bukan di dalam source code.

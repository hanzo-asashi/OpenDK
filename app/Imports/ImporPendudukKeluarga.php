<?php

/*
 * File ini bagian dari:
 *
 * OpenDK
 *
 * Aplikasi dan source code ini dirilis berdasarkan lisensi GPL V3
 *
 * Hak Cipta 2017 - 2023 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 *
 * Dengan ini diberikan izin, secara gratis, kepada siapa pun yang mendapatkan salinan
 * dari perangkat lunak ini dan file dokumentasi terkait ("Aplikasi Ini"), untuk diperlakukan
 * tanpa batasan, termasuk hak untuk menggunakan, menyalin, mengubah dan/atau mendistribusikan,
 * asal tunduk pada syarat berikut:
 *
 * Pemberitahuan hak cipta di atas dan pemberitahuan izin ini harus disertakan dalam
 * setiap salinan atau bagian penting Aplikasi Ini. Barang siapa yang menghapus atau menghilangkan
 * pemberitahuan ini melanggar ketentuan lisensi Aplikasi Ini.
 *
 * PERANGKAT LUNAK INI DISEDIAKAN "SEBAGAIMANA ADANYA", TANPA JAMINAN APA PUN, BAIK TERSURAT MAUPUN
 * TERSIRAT. PENULIS ATAU PEMEGANG HAK CIPTA SAMA SEKALI TIDAK BERTANGGUNG JAWAB ATAS KLAIM, KERUSAKAN ATAU
 * KEWAJIBAN APAPUN ATAS PENGGUNAAN ATAU LAINNYA TERKAIT APLIKASI INI.
 *
 * @package    OpenDK
 * @author     Tim Pengembang OpenDesa
 * @copyright  Hak Cipta 2017 - 2023 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 * @license    http://www.gnu.org/licenses/gpl.html    GPL V3
 * @link       https://github.com/OpenSID/opendk
 */

namespace App\Imports;

use App\Models\DataDesa;
use App\Models\Keluarga;
use App\Models\Penduduk;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImporPendudukKeluarga implements ToCollection, WithHeadingRow, WithChunkReading, ShouldQueue
{
    use Importable;

    /**
     * {@inheritdoc}
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * {@inheritdoc}
     */
    public function collection(Collection $collection)
    {
        $kode_desa = Arr::flatten(DataDesa::pluck('desa_id'));
        DB::beginTransaction(); //multai transaction

        foreach ($collection as $value) {
            if (! in_array($value['desa_id'], $kode_desa)) {
                Log::debug('Desa tidak terdaftar');

                DB::rollBack(); // rollback data yang sudah masuk karena ada data yang bermasalah
                Storage::deleteDirectory('temp'); // Hapus folder temp ketika gagal
                throw  new Exception('kode Desa tidak terdaftar . kode desa yang bermasalah : '. $value['desa_id']);
            }

            // Data Keluarga
            if ($value['hubungan_keluarga'] == 1) {
                $keluarga = [
                    'no_kk'                 => $value['nomor_kk'],
                    'nik_kepala'            => $value['nomor_nik'],
                    'tgl_daftar'            => $value['tgl_daftar'] ?? now(),
                    'tgl_cetak_kk'          => $value['tgl_cetak_kk'] ?? now(),
                    'alamat'                => $value['alamat'],
                    'dusun'                 => $value['dusun'],
                    'rw'                    => $value['rw'],
                    'rt'                    => $value['rt'],
                    'desa_id'               => $value['desa_id'],
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];

                Keluarga::updateOrInsert([
                    'desa_id'      => $keluarga['desa_id'],
                    'no_kk'        => $keluarga['no_kk']
                ], $keluarga);
            }

            // Data Penduduk
            $penduduk = [
                'nik'                   => $value['nomor_nik'],
                'nama'                  => $value['nama'],
                'no_kk'                 => $value['nomor_kk'],
                'sex'                   => $value['jenis_kelamin'],
                'tempat_lahir'          => $value['tempat_lahir'],
                'tanggal_lahir'         => $value['tanggal_lahir'],
                'agama_id'              => $value['agama'],
                'pendidikan_kk_id'      => $value['pendidikan_dlm_kk'],
                'pendidikan_sedang_id'  => $value['pendidikan_sdg_ditempuh'],
                'pekerjaan_id'          => $value['pekerjaan'],
                'status_kawin'          => $value['kawin'],
                'kk_level'              => $value['hubungan_keluarga'],
                'warga_negara_id'       => $value['kewarganegaraan'],
                'nama_ibu'              => $value['nama_ibu'],
                'nama_ayah'             => $value['nama_ayah'],
                'golongan_darah_id'     => $value['gol_darah'],
                'akta_lahir'            => $value['akta_lahir'],
                'dokumen_pasport'       => $value['nomor_dokumen_pasport'],
                'tanggal_akhir_pasport' => $value['tanggal_akhir_pasport'],
                'dokumen_kitas'         => $value['nomor_dokumen_kitas'],
                'ayah_nik'              => $value['nik_ayah'],
                'ibu_nik'               => $value['nik_ibu'],
                'akta_perkawinan'       => $value['nomor_akta_perkawinan'],
                'tanggal_perkawinan'    => $value['tanggal_perkawinan'],
                'akta_perceraian'       => $value['nomor_akta_perceraian'],
                'tanggal_perceraian'    => $value['tanggal_perceraian'],
                'cacat_id'              => $value['cacat'],
                'cara_kb_id'            => $value['cara_kb'],
                'hamil'                 => $value['hamil'],

                // Tambahan
                'foto'            => $value['foto'],
                'alamat_sekarang' => $value['alamat_sekarang'],
                'alamat'          => $value['alamat'],
                'dusun'           => $value['dusun'],
                'rw'              => $value['rw'],
                'rt'              => $value['rt'],
                'desa_id'         => $value['desa_id'],
                'status_dasar'    => $value['status_dasar'],
                'status_rekam'    => $value['status_rekam'],
                'created_at'      => $value['created_at'],
                'updated_at'      => $value['updated_at'],
                'imported_at'     => now(),
            ];

            Penduduk::updateOrInsert([
                'desa_id'      => $penduduk['desa_id'],
                'nik'          => $penduduk['nik'],
            ], $penduduk);

            if ($value['foto']) {
                $temp_foto = 'temp/penduduk/foto/' . $value['foto'];
                $public_foto = 'public/penduduk/foto/' . $value['foto'];

                // Salin file yang dibutuhkan
                if (Storage::exists($temp_foto)) {
                    // Hapus file yang lama
                    if (Storage::exists($public_foto)) {
                        Storage::delete($public_foto);
                    }

                    Storage::copy($temp_foto, $public_foto);
                }
            }
        }
        DB::commit(); // commit data dan simpan ke dalam database

        // Hapus folder temp ketika sudah selesai
        Storage::deleteDirectory('temp');
    }
}

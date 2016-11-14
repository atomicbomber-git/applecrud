<?php
/* Expects $app, an instance of Slim\App */

/* Eloquent ORM models */
use App\Model\Tool;
use App\Model\Card;
use App\Model\Land;
use App\Model\Building;
use App\Model\JIJ;
use App\Model\ATL;
use App\Model\Room;
use App\Model\Property;

use App\Middleware\AuthorizationMiddleware;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jenssegers\Date\Date;
use PHPassLib\Hash\BCrypt;

/* Login Page */
$app->get("/login", function ($req, $res) {

    if (isset($_SESSION['log_in'])) {
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/');
    }

    $error_message = null;
    if (isset($_SESSION["login_error"])) {
        $error_message = $_SESSION["login_error"];
    }
        
    unset($_SESSION["login_error"]);

    return $this->view->render($res, 'login.twig', ['error' => $error_message]);
});

$app->post("/login", function ($req, $res) {

    /* Retrieve stored user data */
    $user_data = json_decode(file_get_contents('password.json'));

    $post_data = $req->getParsedBody();
    if ($post_data["username"] !== $user_data->username) {
        $_SESSION["login_error"] = "Wrong username or password.";
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/login');
    }

    if (! BCrypt::verify($post_data["password"], $user_data->password)) {
        $_SESSION["login_error"] = "Wrong username or password.";
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/login');
    }

    $_SESSION['log_in'] = true;

    return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/');
});

$app->get("/edit_account", function ($req, $res) {

    $user_data = json_decode(file_get_contents('password.json'));
    $old_username = $user_data->username;

    $success_message = null;
    $error_message = null;
    if ( isset($_SESSION['success_message']) ) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    if ( isset($_SESSION['error_message']) ) {
        $error_message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }

    return $this->view->render(
        $res, "edit_account.twig",
        ['old_username' => $old_username, 'success_message' => $success_message, 'error_message' => $error_message]);
});

$app->post("/edit_account", function ($req, $res) {

    $post_data = $req->getParsedBody();
    $user_data = json_decode(file_get_contents('password.json'));

    if ( ! $post_data['username'] ) {
        $_SESSION['error_message'] = "Nama pengguna tidak boleh kosong.";
        return $res->withStatus(302)->withHeader('Location', '/edit_account');
    }

    if ( ! $post_data['old_password'] ) {
        $_SESSION['error_message'] = "Kata sandi lama tidak boleh kosong.";
        return $res->withStatus(302)->withHeader('Location', '/edit_account');
    }

    if ( ! $post_data['new_password'] ) {
        $_SESSION['error_message'] = "Kata sandi baru tidak boleh kosong.";
        return $res->withStatus(302)->withHeader('Location', '/edit_account');
    }

    if ( ! BCrypt::verify($post_data['old_password'], $user_data->password) ) {
        $_SESSION['error_message'] = "Kata sandi lama Anda keliru.";
        return $res->withStatus(302)->withHeader('Location', '/edit_account');
    }

    $user_data->username = $post_data['username'];
    $user_data->password = BCrypt::hash($post_data['new_password']);
    file_put_contents('password.json', json_encode($user_data));

    $_SESSION['success_message'] = "Data berhasil diubah.";
    return $res->withStatus(302)->withHeader('Location', '/edit_account');
});

$app->get("/logout", function ($req, $res) {
    session_destroy();
    return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/');
});

/* Home Page */
$app->get("/", function ($req, $res) {

    $is_logged_in = false;

    if (isset($_SESSION['log_in'])) {
        $is_logged_in = true;
    }

    return $this->view->render($res, 'home.twig', ['is_logged_in' => $is_logged_in]);
});

$app->group("/tanah", function() {

    $this->get("/display_manage", function ($req, $res, $args) {
        $table = Land::get();

        foreach ($table as $row) {
            
            if ($row->tanggal === '0000-00-00') {
                $row->tanggal = '-';
            }
            else {
                $date = new Date($row->tanggal);
                $row->tanggal = $date->format('d/m/Y');
            }
        }

        /* Query for sums of several columns  */
        $total = Land::select(
            Capsule::raw("
                SUM(luas) AS luas,
                SUM(harga) AS harga
            ")
        )->first();

        return $this->view->render($res, 'tanah_manage.twig', ['table' => $table, 'total' => $total]);
    });

    $this->get("/add", function ($req, $res, $args) {
        return $this->view->render($res, 'tanah_add.twig');
    });

    $this->post("/add", function ($req, $res, $args) {
        
        $land_record = new Land();
        
        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $land_record->$key = $value;
        }

        $land_record->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/display_manage');
    });

    $this->get("/edit/{id}", function ($req, $res, $args) {
        $data = Land::find($args["id"]);

        return $this->view->render($res, 'tanah_edit.twig', ['data' => $data]);
    });

    $this->post("/edit/{id}", function ($req, $res, $args) {
        $land_record = Land::find($args["id"]);

        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $land_record->$key = $value;
        }

        $land_record->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/display_manage');
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        $land_record = Land::find($args["id"]);
        unlink("./public/images/tanah/$land_record->image");
        $land_record->delete();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/display_manage');
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = Land::find($args['id']);

        return $this->view->render($res, 'tanah_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/tanah/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/tanah/photo/' . $args['id'] );
        }

        $data = Land::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/tanah/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = Land::find($args['id']);

        unlink("./public/images/tanah/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/tanah/photo/$data->no"
        );
    });
})->add(new AuthorizationMiddleware());

$app->group("/barang_dan_mesin", function() {
    $this->get("/display_manage", function ($req, $res) {
        $data = Tool::get();
        foreach ($data as $item) {
            $item->jumlah_penyusutan = number_format($item->jumlah_penyusutan, 2, ",",  "." );
            $item->nilai_perolehan = number_format($item->nilai_perolehan, 2, ",",  "." );
            $item->nilai_buku = number_format($item->nilai_buku, 2, ",",  "." );
            $item->persen = $item->persen . "%";
        }

        /* Query for sums of several columns  */
        $total = Tool::select(
            Capsule::raw("
                SUM(nilai_perolehan) AS nilai_perolehan,
                SUM(jumlah_penyusutan) AS jumlah_penyusutan,
                SUM(nilai_buku) AS nilai_buku
            ")
        )->first();

        $total->nilai_perolehan = number_format($total->nilai_perolehan, 2, ",",  "." );
        $total->jumlah_penyusutan = number_format($total->jumlah_penyusutan, 2, ",",  "." );
        $total->nilai_buku = number_format($total->nilai_buku, 2, ",",  "." );

        return $this->view->render(
            $res,
            'barang_dan_mesin_manage.twig', ["data" => $data, "total" => $total]);
    });

    $this->get("/add", function ($req, $res) {
        return $this->view->render($res, 'barang_dan_mesin_add.twig', ['current_year' => date("Y")]);
    });

    $this->post("/add", function ($req, $res) {
        $data = $req->getParsedBody();
        $tool = new Tool();
        $tool->kode = $data['kode'];
        $tool->nama = $data['nama'];
        $tool->no_register = $data['no_register'];
        $tool->merek = $data['merek'];
        $tool->ukuran = $data['ukuran'];
        $tool->bahan = $data['bahan'];
        $tool->tahun = $data['tahun'];
        $tool->pabrik = $data['pabrik'];
        $tool->rangka = $data['rangka'];
        $tool->mesin = $data['mesin'];
        $tool->polisi = $data['polisi'];
        $tool->bpkb = $data['bpkb'];
        $tool->asal_usul = $data['asal_usul'];
        $tool->masa_manfaat = $data['masa_manfaat'];
        $tool->sisa_umur = $data['sisa_umur'];
        $tool->nilai_perolehan = $data['nilai_perolehan'];
        $tool->jumlah_penyusutan = $data['jumlah_penyusutan'];
        $tool->nilai_buku = $data['nilai_buku'];
        $tool->persen = $data['persen'];
        $tool->kondisi_barang = $data['kondisi_barang'];
        $tool->keterangan = $data['keterangan'];
        $tool->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/display_manage');
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        
        try {
            $record = Tool::findOrFail($args["id"]);
        }

        catch (ModelNotFoundException $e) {
            return $this->view->render($res->withStatus(404), "not_found_error.twig");
        }

        unlink("./public/images/peralatan/$record->image");
        $record->delete();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/display_manage');
    });

    $this->get("/edit/{id}", function ($req, $res, $args) {

        try {
            $record = Tool::findOrFail($args["id"]);
        }

        catch (ModelNotFoundException $e) {
            return $this->view->render($res->withStatus(404), "not_found_error.twig");
        }

        return $this->view->render($res, "barang_dan_mesin_edit.twig", ["data" => $record]);
    });

    $this->post("/edit/{id}", function ($req, $res, $args) {
        $data = $req->getParsedBody();
    
        try {
            $tool = Tool::findOrFail($args["id"]);
        } 
        catch (ModelNotFoundException $e) {
            return $this->view->render($res->withStatus(404), "not_found_error.twig");
        }

        $tool->kode = $data['kode'];
        $tool->nama = $data['nama'];
        $tool->no_register = $data['no_register'];
        $tool->merek = $data['merek'];
        $tool->ukuran = $data['ukuran'];
        $tool->bahan = $data['bahan'];
        $tool->tahun = $data['tahun'];
        $tool->pabrik = $data['pabrik'];
        $tool->rangka = $data['rangka'];
        $tool->mesin = $data['mesin'];
        $tool->polisi = $data['polisi'];
        $tool->bpkb = $data['bpkb'];
        $tool->asal_usul = $data['asal_usul'];
        $tool->masa_manfaat = $data['masa_manfaat'];
        $tool->sisa_umur = $data['sisa_umur'];
        $tool->nilai_perolehan = $data['nilai_perolehan'];
        $tool->jumlah_penyusutan = $data['jumlah_penyusutan'];
        $tool->nilai_buku = $data['nilai_buku'];
        $tool->persen = $data['persen'];
        $tool->kondisi_barang = $data['kondisi_barang'];
        $tool->keterangan = $data['keterangan'];
        $tool->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/display_manage');
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = Tool::find($args['id']);

        return $this->view->render($res, 'barang_dan_mesin_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/peralatan/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/barang_dan_mesin/photo/' . $args['id'] );
        }

        $data = Tool::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/barang_dan_mesin/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = Tool::find($args['id']);

        unlink("./public/images/peralatan/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/barang_dan_mesin/photo/$data->no"
        );
    });
})->add(new AuthorizationMiddleware());

$app->group("/gedung_dan_bangunan", function() {

    $this->get("/display_manage", function ($req, $res) {
        $table = Building::get();
        foreach ($table as $row) {
            $row->jumlah_penyusutan = number_format($row->jumlah_penyusutan, 2, ",",  "." );
            $row->harga = number_format($row->harga, 2, ",",  "." );
            $row->nilai_buku = number_format($row->nilai_buku, 2, ",",  "." );
            
            if ($row->tanggal === '0000-00-00') {
                $row->tanggal = '-';
            }
            else {
                $date = new Date($row->tanggal);
                $row->tanggal = $date->format('d/m/Y');
            }

        }

        /* Query for sums of several columns  */
        $total = Building::select(
            Capsule::raw("
                SUM(harga) AS harga,
                SUM(jumlah_penyusutan) AS jumlah_penyusutan,
                SUM(nilai_buku) AS nilai_buku
            ")
        )->first();

        $total->harga = number_format($total->harga, 2, ",",  "." );
        $total->jumlah_penyusutan = number_format($total->jumlah_penyusutan, 2, ",",  "." );
        $total->nilai_buku = number_format($total->nilai_buku, 2, ",",  "." );

        return $this->view->render(
            $res, 'gedung_dan_bangunan_manage.twig',
            ['table' => $table, 'total' => $total]
        );
    });

    $this->get("/add", function ($req, $res) {
        return $this->view->render($res, 'gedung_dan_bangunan_add.twig', ['date' => date("Y-m-d")]);
    });

    $this->post("/add", function ($req, $res) {

        $building = new Building();

        $post_data = $req->getParsedBody();

        foreach ($post_data as $key => $value) {
            $building->$key = $value;
        }

        $building->save();
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/display_manage');
    });

    $this->get("/edit/{id}", function ($req, $res, $args) {
        $bangunan = Building::find($args["id"]);

        return $this->view->render($res, 'gedung_dan_bangunan_edit.twig',
            ["data" => $bangunan]
        );
    });

    $this->post("/edit/{id}", function ($req, $res, $args) {
        $bangunan = Building::find($args["id"]);

        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $bangunan->$key = $value;
        }

        $bangunan->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/display_manage');
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        $building = Building::find($args["id"]);
        unlink("./public/images/gedung/$building->image");
        $building->delete();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/display_manage');
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = Building::find($args['id']);

        return $this->view->render($res, 'gedung_dan_bangunan_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/gedung/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/photo/' . $args['id'] );
        }

        $data = Building::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/gedung_dan_bangunan/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = Building::find($args['id']);

        unlink("./public/images/gedung/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/gedung_dan_bangunan/photo/$data->no"
        );
    });

})->add(new AuthorizationMiddleware());

$app->group("/jalan_irigasi_jaringan", function() {
    
    $this->get("/display_manage", function ($req, $res) {

        $table = JIJ::get();
        Date::setLocale('en');
        foreach ($table as $row) {
            $row->harga = number_format($row->harga, 2, ",",  "." );
            $row->nilai_buku = number_format($row->nilai_buku, 2, ",",  "." );
        }

        /* Query for sums of several columns  */
        $total = JIJ::select(
            Capsule::raw("
                SUM(harga) AS harga,
                SUM(nilai_buku) AS nilai_buku
            ")
        )->first();
        $total->harga = number_format($total->harga, 2 , ",", ".");
        $total->nilai_buku = number_format($total->nilai_buku, 2 , ",", ".");

        return $this->view->render($res, 'jig_manage.twig', ['table' => $table, 'total' => $total]);
    });

    $this->get("/add", function ($req, $res) {
        return $this->view->render($res, 'jig_add.twig');  
    });

    $this->post("/add", function ($req, $res) {

        $record = new JIJ();

        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $record->$key = $value;
        }

        $record->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/display_manage');

    });

    $this->get("/edit/{id}", function ($req, $res, $args) {
        $data = JIJ::find($args["id"]);

        return $this->view->render($res, 'jig_edit.twig', ['data' => $data]);
    });

    $this->post("/edit/{id}", function ($req, $res, $args) {
        $data = JIJ::find($args["id"]);

        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $data->$key = $value;
        }

        $data->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/display_manage');
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        $data = JIJ::find($args["id"]);
        unlink("./public/images/jij/$data->image");
        $data->delete();
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/display_manage');
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = JIJ::find($args['id']);

        return $this->view->render($res, 'jig_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/jij/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/photo/' . $args['id'] );
        }

        $data = JIJ::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/jalan_irigasi_jaringan/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = JIJ::find($args['id']);

        unlink("./public/images/jij/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/jalan_irigasi_jaringan/photo/$data->no"
        );
    });

})->add(new AuthorizationMiddleware());

$app->group("/aset_tetap_lainnya", function() {
    $this->get("/display_manage", function ($req, $res) {

        /* Retrieve data table */
        $table = ATL::get();
        foreach ($table as $row) {
            $row->harga = number_format($row->harga, 2, ",", ".");
            $row->nilai = number_format($row->nilai, 2, ",", ".");
        }

        /* Query for sums of several columns  */
        $total = ATL::select(
            Capsule::raw("
                SUM(harga) AS harga,
                SUM(nilai) AS nilai
            ")
        )->first();
        $total->harga = number_format($total->harga, 2 , ",", ".");
        $total->nilai = number_format($total->nilai, 2 , ",", ".");

        return $this->view->render($res, 'atl_manage.twig', ['table' => $table, 'total' => $total]);
    });

    $this->get("/add", function ($req, $res) {
        return $this->view->render($res, 'atl_add.twig');
    });

    $this->post("/add", function ($req, $res) {
        $record = new ATL();
        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $record->$key = $value;
        }
        $record->save();
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/display_manage');
    });

    $this->get("/edit/{id}", function ($req, $res, $args) {
        $data = ATL::find($args["id"]);
        return $this->view->render($res, 'atl_edit.twig', ["data" => $data]);
    });

    $this->post("/edit/{id}", function ($req, $res, $args) {
        $data = ATL::find($args["id"]);

        $post_data = $req->getParsedBody();
        foreach ($post_data as $key => $value) {
            $data->$key = $value;
        }
        $data->save();

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/display_manage');
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        $data = ATL::find($args["id"]);
        unlink("./public/images/atl/$data->image");
        $data->delete();
        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/display_manage');
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = ATL::find($args['id']);

        return $this->view->render($res, 'atl_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/atl/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/photo/' . $args['id'] );
        }

        $data = ATL::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/aset_tetap_lainnya/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = ATL::find($args['id']);

        unlink("./public/images/atl/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/aset_tetap_lainnya/photo/$data->no"
        );
    });
})->add(new AuthorizationMiddleware());

// $app->group("/barang_ruangan", function() {
//     $this->get("/display_manage", function ($req, $res) {

//         $table = Property::get();

//         foreach ($table as $row) {
//             if ($row->tahun === 0) { $row->tahun = "-"; }
//         }

//         return $this->view->render($res, 'room_manage.twig', ['table' => $table]);

//     });

//     $this->get("/add", function ($req, $res) {
//         return $this->view->render(
//             $res,
//             'room_add.twig',
//             ['current_year' => date('Y')]
//         );
//     });

//     $this->post("/add", function ($req, $res) {
        
//         $newData = new Property;
//         $postData = $req->getParsedBody();
//         foreach ($postData as $key => $value) {
//             $newData->$key = $value;
//         }
//         $newData->save();

//         return $res
//             ->withStatus(302)
//             ->withHeader(
//                 'Location',
//                 $req->getUri()->getBaseUrl() . '/ruangan/display_manage'
//             );
//     });

//     $this->get("/edit/{id}", function ($req, $res, $args) {
//         $data = Property::find($args["id"]);
//         return $this->view->render($res, 'room_edit.twig', ['data' => $data]);
//     });

//     $this->post("/edit/{id}", function ($req, $res, $args) {
        
//         $data = Property::find($args['id']);
//         $postBody = $req->getParsedBody();
//         foreach ($postBody as $key => $value) {
//             $data->$key = $value;
//         }
//         $data->save();

//         return $res
//             ->withStatus(302)
//             ->withHeader(
//                 'Location',
//                 $req->getUri()->getBaseUrl() . '/ruangan/display_manage'
//             );
//     });

//     $this->get("/delete/{id}", function ($req, $res, $args) {

//         $data = Property::find($args['id']);
//         $data->delete();

//         return $res
//             ->withStatus(302)
//             ->withHeader(
//                 'Location',
//                 $req->getUri()->getBaseUrl() . '/ruangan/display_manage'
//             );
//     });
// })->add(new AuthorizationMiddleware());

$app->group("/ruangan", function() {
    

    $this->post("/add", function ($req, $res, $args) {

        $data = $req->getParsedBody();
        if ( ! $data["name"] ) {
            $_SESSION["error"] = "Kolom nama wajib diisi!";
            return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/ruangan/manage#alert");
        }

        /* Metadata card to for the room */
        $card = new Card;
        $card->save();

        $room = new Room;
        $room->name = $data["name"];
        $room->card_id = $card->id;
        $room->save();

        $_SESSION["success"] = "Data berhasil ditambahkan.";
        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/ruangan/manage#alert");
    });

    $this->post("/edit_name/{id}", function ($req, $res, $args) {
        $room = Room::find($args["id"]);
        $room->name = $req->getParsedBody()["name"];
        $room->save();
        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/property/manage/$args[id]");
    });

    $this->get("/delete/{id}", function ($req, $res, $args) {
        $room = Room::find($args["id"]);
        $room->delete();

        $_SESSION["success"] = "Data berhasil dihapus.";
        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/ruangan/manage#alert");
    });

    $this->get("/edit_metadata/{id}", function ($req, $res, $args) {

        $room = Room::find($args["id"]);
        $metadata = Card::find($room->card_id);
        
        $message = null;
        if (isset($_SESSION['message_success'])) {
            $message = $_SESSION['message_success'];
            unset($_SESSION['message_success']);
        } 

        return $this->view->render(
            $res, "ruangan_metadata.twig",
            ['metadata' => $metadata, 'message_success' => $message, 'room' => $room]);
    });

    $this->post("/edit_metadata/{id}", function ($req, $res, $args) {
        
        $room = Room::find($args["id"]);
        $metadata = Card::find($room->card_id);

        $post = $req->getParsedBody();

        foreach ($post as $key => $value) {
            $metadata->$key = $value;
        }

        $metadata->save();

        $_SESSION['message_success'] = "Data berhasil diubah.";

        return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/ruangan/edit_metadata/' . $room->id);
    });
})->add(new AuthorizationMiddleware());

$app->get("/ruangan/manage", function ($req, $res, $args) {
    /* Handle messages that were stored before we were redirected to this URL */
    $message = null;
    if  ( isset($_SESSION["error"]) ) {
        $message["error"] = $_SESSION["error"];
        unset($_SESSION["error"]);
    }

    if  ( isset($_SESSION["success"]) ) {
        $message["success"] = $_SESSION["success"];
        unset($_SESSION["success"]);
    }

    /* Check if we're logged in */
    $is_logged_in = false;
    if (isset($_SESSION['log_in'])) {
        $is_logged_in = true;
    }

    /* Retrieve table data */
    $table = Room::get();

    return $this->view->render($res, "room_manage.twig", ["message" => $message, "table" => $table, "is_logged_in" => $is_logged_in]);
});

$app->get("/ruangan/print/{id}", function ($req, $res, $args) {

    $room = Room::find($args["id"]);
    $table = $room->properties;
    $metadata = Card::find($room->card_id);

    return $this->view->render($res, "property_print.twig", ["table" => $table, "metadata" => $metadata, "room" => $room]);
});

$app->group("/property", function () {

    $this->get("/manage/{id}", function ($req, $res, $args) {

        $room = Room::find($args["id"]);
        $table = $room->properties;

        return $this->view->render($res, "property_manage.twig", ["table" => $table, "room" => $room]);
    });

    $this->get("/add/{id}", function ($req, $res, $args) {
        return $this->view->render($res, "property_add.twig", ["room_id" => $args["id"]]);
    });

    $this->post("/add/{id}", function ($req, $res, $args) {

        $data = $req->getParsedBody();

        Room::find($args["id"])
            ->properties()
            ->save(new Property($data));

        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/property/manage/$args[id]");

    });

    $this->get("/edit/{room_id}/{prop_id}", function ($req, $res, $args) {

        $data = Property::find($args["prop_id"]);
        $this->view->render($res, "property_edit.twig", ["data" => $data]);

    });

    $this->post("/edit/{room_id}/{prop_id}", function ($req, $res, $args) {

        Property::find($args["prop_id"])
            ->update( $req->getParsedBody() );

        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/property/manage/$args[room_id]");
    });

    $this->get("/delete/{room_id}/{prop_id}", function ($req, $res, $args) {
        Property::find($args["prop_id"])->delete();
        return $res->withStatus(302)->withHeader("Location", $req->getUri()->getBaseUrl() . "/property/manage/$args[room_id]");
    });

    $this->get("/photo/{id}", function ($req, $res, $args) {
        
        /* In case we're redirected here due to an error */
        $error = null;
        if (isset($_SESSION['error'])) {
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        /* In case we have a message */
        $message = null;
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }

        $data = Property::find($args['id']);

        return $this->view->render($res, 'property_photo.twig', ['data' => $data, 'error' => $error, 'message' => $message]);
    });

    $this->post("/photo/{id}", function ($req, $res, $args) {

        $files = $req->getUploadedFiles();
        if (empty($files['picture'])) {
            /* Error */
            $_SESSION['error'] = 'Error: Kolom file gambar wajib diisi.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/property/photo/' . $args['id'] );
        }

        $file = $files['picture'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            /* Error */
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/property/photo/' . $args['id'] );
        }

        $old_filename = $file->getClientFilename();
        $extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        $new_filename = uniqid() . ".$extension";

        try {
            $file->moveTo("./public/images/property/$new_filename");
        }
        catch (Exception $e) {
            $_SESSION['error'] = 'Error: Terjadi kesalahan pada saat menyimpan data.';
            return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/property/photo/' . $args['id'] );
        }

        $data = Property::find($args['id']);
        $data->image = $new_filename;
        $data->save();

        $_SESSION['message'] = 'Gambar berhasil ditambahkan';

        return $res->withStatus(302)->withHeader('Location',
            $req->getUri()->getBaseUrl() . '/property/photo/' . $args['id']
        );
    });

    $this->get("/photo/delete/{id}", function ($req, $res, $args) {

        $data = Property::find($args['id']);

        unlink("./public/images/property/$data->image");

        $data->image = "";
        $data->save();

        $_SESSION['message'] = "Berhasil menghapus data";
        return $res->withStatus(302)->withHeader('Location', 
            $req->getUri()->getBaseUrl() . "/property/photo/$data->id"
        );
    });

})->add(new AuthorizationMiddleware());

$app->get("/edit_metadata/{id}", function ($req, $res, $args) {
    $metadata = Card::find($args["id"]);
    
    $message = null;
    if (isset($_SESSION['message_success'])) {
        $message = $_SESSION['message_success'];
        unset($_SESSION['message_success']);
    } 

    return $this->view->render(
        $res, "barang_dan_mesin_metadata.twig",
        ['metadata' => $metadata, 'message_success' => $message]);
})->add(new AuthorizationMiddleware());

$app->post("/edit_metadata/{id}", function ($req, $res, $args) {
    $metadata = Card::find($args["id"]);

    $post = $req->getParsedBody();

    foreach ($post as $key => $value) {
        $metadata->$key = $value;
    }

    $metadata->save();

    $_SESSION['message_success'] = "Data berhasil diubah.";

    return $res->withStatus(302)->withHeader('Location', $req->getUri()->getBaseUrl() . '/edit_metadata/' . $metadata->id);
})->add(new AuthorizationMiddleware());

$app->get("/tanah/display_print", function ($req, $res) {

    $table = Land::get();
    foreach ($table as $row) {
        $row->jumlah_penyusutan = number_format($row->jumlah_penyusutan, 2, ",",  "." );
        $row->nilai_buku = number_format($row->nilai_buku, 2, ",",  "." );

        if ($row->tanggal === '0000-00-00') {
            $row->tanggal = '-';
        }
        else {
            $date = new Date($row->tanggal);
            $row->tanggal = $date->format('d/m/Y');
        }

    }

    /* Query for sums of several columns  */
    $total = Land::select(
        Capsule::raw("
            SUM(luas) AS luas,
            SUM(harga) AS harga
            ")
        )->first();

    $total->jumlah_penyusutan = number_format($total->jumlah_penyusutan, 2, ",",  "." );
    $total->nilai_buku = number_format($total->nilai_buku, 2, ",",  "." );

    /* Query for metadata */
    $metadata = Card::find(1);

    Date::setLocale('id');
    $date = new Date($metadata->tanggal);
    $metadata->tanggal = $date->format('j F Y');

    return $this->view->render(
        $res, 'tanah_display.twig',
        ['table' => $table, 'metadata' => $metadata, 'total' => $total]
        );
});

$app->get("/barang_dan_mesin/display_print", function ($req, $res) {
    $table = Tool::get();

    foreach ($table as $row) {
        $row->nilai_perolehan = number_format($row->nilai_perolehan, 2, ",",  "." );
        $row->jumlah_penyusutan = number_format($row->jumlah_penyusutan, 2, ",",  "." );
        $row->nilai_buku = number_format($row->nilai_buku, 2, ",",  "." );

        if ($row->tahun === 0) {
            $row->tahun = '-';
        }
    }

    /* Query for metadata */
    $metadata = Card::find(2);

    Date::setLocale('id');
    $date = new Date($metadata->tanggal);
    $metadata->tanggal = $date->format('j F Y');

    /* Query for sums of several columns  */
    $total = Tool::select(
        Capsule::raw("
            SUM(nilai_perolehan) AS nilai_perolehan,
            SUM(jumlah_penyusutan) AS jumlah_penyusutan,
            SUM(nilai_buku) AS nilai_buku
        ")
    )->first();

    $total->nilai_perolehan = number_format($total->nilai_perolehan, 2, ",",  "." );
    $total->jumlah_penyusutan = number_format($total->jumlah_penyusutan, 2, ",",  "." );
    $total->nilai_buku = number_format($total->nilai_buku, 2, ",",  "." );


    return $this->view->render($res, 'barang_dan_mesin_display.twig',
        ['table' => $table, 'metadata' => $metadata, 'total' => $total]);
});

$app->get("/gedung_dan_bangunan/display_print", function ($req, $res) {
    /* Obtain and format table data */
    $table = Building::get();
    Date::setLocale('en');
    foreach ($table as $row) {
        $row->harga = number_format($row->harga, 2, ",",  "." );
        $row->jumlah_penyusutan = number_format($row->jumlah_penyusutan, 2, ",",  "." );
        $row->nilai_buku = number_format($row->nilai_buku, 2, ",",  "." );
        
        if ($row->tanggal === '0000-00-00') {
            $row->tanggal = '-';
        }
        else {
            $date = new Date($row->tanggal);
            $row->tanggal = $date->format('d/m/Y');
        }

    }

    /* Query for metadata */
    $metadata = Card::find(3);
    Date::setLocale('id');
    $date = new Date($metadata->tanggal);
    $metadata->tanggal = $date->format('j F Y');

    /* Query for sums of several columns  */
    $total = Building::select(
        Capsule::raw("
            SUM(harga) AS harga,
            SUM(jumlah_penyusutan) AS jumlah_penyusutan,
            SUM(nilai_buku) AS nilai_buku
            ")
        )->first();
    $total->harga = number_format($total->harga, 2 , ",", ".");
    $total->jumlah_penyusutan = number_format($total->jumlah_penyusutan, 2 , ",", ".");
    $total->nilai_buku = number_format($total->nilai_buku, 2 , ",", ".");

    return $this->view->render($res, 'gedung_dan_bangunan_display.twig',
        ['table' => $table, 'metadata' => $metadata, 'total' => $total]);
});

$app->get("/jalan_irigasi_jaringan/display_print", function ($req, $res) {
    $metadata = Card::find(4);

    /* Retrieve data table */
    $table = JIJ::get();
    foreach ($table as $row) {
        $row->harga = number_format($row->harga, 2, ",", ".");
        $row->nilai_buku = number_format($row->nilai_buku, 2, ",", ".");

        

    }

    /* Query for sums of several columns  */
    $total = JIJ::select(
        Capsule::raw("
            SUM(harga) AS harga,
            SUM(nilai_buku) AS nilai_buku
            ")
        )->first();
    $total->harga = number_format($total->harga, 2 , ",", ".");
    $total->nilai_buku = number_format($total->nilai_buku, 2 , ",", ".");

    return $this->view->render($res, 'jig_display.twig', ['metadata' => $metadata, 'table' => $table, 'total' => $total]);
});

$app->get("/aset_tetap_lainnya/display_print", function ($req, $res) {

    $table = ATL::get();
    foreach ($table as $row) {
        $row->harga = number_format($row->harga, 2, ",",  "." );
        $row->nilai = number_format($row->nilai, 2, ",",  "." );

        if ($row->tanggal === '0000-00-00') {
            $row->tanggal = '-';
        }
        else {
            $date = new Date($row->tanggal);
            $row->tanggal = $date->format('d/m/Y');
        }
        
    }

    /* Query for sums of several columns  */
    $total = ATL::select(
        Capsule::raw("
            SUM(nilai) AS nilai,
            SUM(harga) AS harga
            ")
        )->first();

    $total->nilai = number_format($total->nilai, 2, ",",  "." );
    $total->harga = number_format($total->harga, 2, ",",  "." );

    /* Query for metadata */
    $metadata = Card::find(5);

    Date::setLocale('id');
    $date = new Date($metadata->tanggal);
    $metadata->tanggal = $date->format('j F Y');

    return $this->view->render(
        $res, 'atl_display.twig', 
        ["table" => $table, "total" => $total, "metadata" => $metadata]
        );
});

$app->get("/ruangan/display_print", function ($req, $res) {
    /* Query for metadata */
    $metadata = Card::find(6);

    /* Query for table */
    $table = Property::get();

    return $this->view->render(
        $res,
        'room_display.twig',
        ['table' => $table, 'metadata' => $metadata]
    );
});
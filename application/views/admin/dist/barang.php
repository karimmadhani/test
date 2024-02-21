<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Data Quiz</title>
    <link href="<?php echo base_url() ?>assets/css/styles.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css" rel="stylesheet" crossorigin="anonymous" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand" href="<?= base_url('admin'); ?>">MANAGEMENT</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0" id="sidebarToggle" href="#"><i class="fas fa-bars"></i></button>
        <!-- Navbar Search-->
        <form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0">
            <div class="input-group">
                <input class="form-control" type="text" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2" />
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
        <!-- Navbar-->
        <ul class="navbar-nav ml-auto ml-md-0">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href=""><?= $this->session->userdata('username') ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= base_url('auth/logout') ?>">Logout</a>
                </div>
            </li>
        </ul>
    </nav>
    <div id="layoutSidenav" style="background-color:#E6E6E6">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading">Core</div>
                        <a class="nav-link" href="<?= base_url('admin') ?>">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            Dashboard
                        </a>
                        <div class="sb-sidenav-menu-heading">Interface</div>
                        <a class="nav-link collapsed" href="<?= base_url('admin/user') ?>">
                            <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                            User
                        </a>
                        <a class="nav-link collapsed" href="<?= base_url('admin/jenisBarang') ?>">
                            <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                            Manage Jenis Barang
                        </a>
                        <a class="nav-link collapsed" href="<?= base_url('admin/barang') ?>">
                            <div class="sb-nav-link-icon"><i class="fas fa-book-open"></i></div>
                            Manage Barang
                        </a>
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <div class="content-wrapper">
                <div class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6">
                                <h3 class="m-0 text-dark">DATA BARANG</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid">
                <div class="bg-white rounded" style="padding-left:20px; padding-right:20px; padding-bottom:20px;">
                    <button id="tambah_data" class="btn btn-primary" style="margin-top : 20px; margin-bottom : 20px" data-toggle="modal" data-target="#exampleModal"><strong>+ Tambah data</strong></button>
                    <!-- Modal -->
                    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel"></h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form id="modalForm" action="<?= base_url('admin/insterOrUpdateBarang') ?>" method="post">
                                    <div class="modal-body">
                                        <input type="text" id="id_utama" name="id" value="" hidden>
                                        <div class="form-group">
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label class="d-block">Jenis Barang</label>
                                                    <select id="jenis_barang" class="form-control" aria-label="Default select example" name="jenis_barang">
                                                        <option value="" selected disabled>Pilih Jenis Barang</option>
                                                        <?php foreach ($jenis_barang as $jenis) : ?>
                                                            <option value="<?= $jenis->id_jenis_barang ?>"><?= $jenis->nama_jenis_barang ?></option>
                                                        <?php endforeach ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label class="d-block">Nama Barang</label>
                                                    <input type="text" name="nama" id="nama" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label class="d-block">Harga</label>
                                                    <input type="number" name="harga" id="harga" class="form-control" value="">
                                                </div>
                                                <div class="form-group">
                                                    <label class="d-block">Stok</label>
                                                    <input type="number" name="stok" id="stok" class="form-control" value="">
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        <button type="submit" name="save" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <table class="table text-center table-bordered" rules="none" border="2">
                        <thead class="thead-dark">
                            <tr>
                                <th>No</th>
                                <th>Nama Barang</th>
                                <th>Jenis Barang</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <?php
                        foreach ($barang as $row) : ?>
                            <tbody>
                                <tr>
                                    <td><?= $no++ ?>.</td>
                                    <td><?= htmlspecialchars($row->nama_barang); ?></td>
                                    <td><?= htmlspecialchars($row->jenis_barang); ?></td>
                                    <td><?= $row->harga; ?></td>
                                    <td class="text-left"><?= $row->stok; ?></td>
                                    <td>
                                        <button class="edit_data btn btn-outline-success mb-1 ml-1 btn-sm " data-id="<?= $row->id_barang; ?>" data-toggle="modal" data-target="#exampleModal">Edit</button>
                                        <a href="<?= base_url("admin/deleteBarang/$row->id_barang"); ?>" class="btn btn-outline-danger mb-1 ml-1 btn-sm">Delete</a>
                                    </td>
                                </tr>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <footer class="py-4 mt-auto">
                <div class="container-fluid">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; managemet 2024</div>
                        <div>
                            <a href="#">Privacy Policy</a>
                            &middot;
                            <a href="#">Terms &amp; Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            $('#tambah_data').on('click', () => {
                $('#exampleModalLabel').text('Tambah Data Quiz');
                $('#id_utama').val('')
                $('#jenis_barang').val('')
                $('#nama').val('');
                $('#harga').val('');
                $('#stok').val('');
            });
            $('.edit_data').on('click', function() {
                $('#exampleModalLabel').text('Edit Data Quiz');

                let id = $(this).data('id');
                $.ajax({
                    url: '<?= base_url('admin/barangForEdit/') ?>' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        $('#id_utama').val(res.id)
                        $('#jenis_barang').val(res.id_jenis_barang);
                        $('#nama').val(res.nama_barang);
                        $('#harga').val(res.harga);
                        $('#stok').val(res.stok);
                    }
                });
            });

        });
    </script>
</body>

</html>
<form action="<?= base_url('Auth/login') ?>" method="POST">
  <div class="mb-3">
    <p><?= $this->session->flashdata('gagal') ?></p>
    <label for="exampleInputEmail1" class="form-label">Username</label>
    <input type="text" name="username" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" required>
  </div>
  <div class="mb-3">
    <label for="exampleInputPassword1" class="form-label">Password</label>
    <input type="password" name="password" class="form-control" id="exampleInputPassword1" required>
  </div>
  <button type="submit" class="btn btn-primary">Submit</button>
</form>
<a href="<?= base_url('Auth/register') ?>">register</a>
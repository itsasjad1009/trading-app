<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase {
 public function createApplication() {
  $app=parent::createApplication();
  if(!\App\Support\DatabaseSafety::isolatedTests())throw new \RuntimeException('Tests require verified isolated database credentials; refusing refresh');
  return $app;
 }
}

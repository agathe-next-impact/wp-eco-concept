<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for EcoDiag_Front_Popup::can_view (private, accessed via Reflection).
 */
final class FrontPopupTest extends TestCase {

    private static \ReflectionMethod $method;
    private EcoDiag_Front_Popup $popup;

    public static function setUpBeforeClass(): void {
        self::$method = new \ReflectionMethod( EcoDiag_Front_Popup::class, 'can_view' );
    }

    protected function setUp(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = false;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array() );
        $GLOBALS['_ecodiag_test_capabilities']   = array();

        $this->popup = new EcoDiag_Front_Popup();
    }

    protected function tearDown(): void {
        unset(
            $GLOBALS['_ecodiag_test_user_logged_in'],
            $GLOBALS['_ecodiag_test_is_admin'],
            $GLOBALS['_ecodiag_test_current_user'],
            $GLOBALS['_ecodiag_test_capabilities']
        );
    }

    private function canView(): bool {
        return self::$method->invoke( $this->popup );
    }

    // ─── Not logged in ──────────────────────────────

    #[Test]
    public function returns_false_when_not_logged_in(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = false;
        $this->assertFalse( $this->canView() );
    }

    // ─── Admin area ─────────────────────────────────

    #[Test]
    public function returns_false_in_admin_area(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = true;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'administrator' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        $this->assertFalse( $this->canView() );
    }

    // ─── Subscriber exclusion ───────────────────────

    #[Test]
    public function returns_false_for_subscriber(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'subscriber' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => false );
        $this->assertFalse( $this->canView() );
    }

    // ─── No edit_posts capability ───────────────────

    #[Test]
    public function returns_false_without_edit_posts_capability(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'custom_role' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => false );
        $this->assertFalse( $this->canView() );
    }

    // ─── Valid users ────────────────────────────────

    #[Test]
    public function returns_true_for_administrator(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'administrator' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        $this->assertTrue( $this->canView() );
    }

    #[Test]
    public function returns_true_for_editor(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'editor' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        $this->assertTrue( $this->canView() );
    }

    #[Test]
    public function returns_true_for_author(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'author' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        $this->assertTrue( $this->canView() );
    }

    #[Test]
    public function returns_true_for_contributor(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'contributor' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        $this->assertTrue( $this->canView() );
    }

    // ─── Multi-role user ────────────────────────────

    #[Test]
    public function returns_false_when_one_role_is_subscriber(): void {
        $GLOBALS['_ecodiag_test_user_logged_in'] = true;
        $GLOBALS['_ecodiag_test_is_admin']       = false;
        $GLOBALS['_ecodiag_test_current_user']   = (object) array( 'roles' => array( 'subscriber', 'editor' ) );
        $GLOBALS['_ecodiag_test_capabilities']   = array( 'edit_posts' => true );
        // subscriber role is checked first → excluded
        $this->assertFalse( $this->canView() );
    }
}

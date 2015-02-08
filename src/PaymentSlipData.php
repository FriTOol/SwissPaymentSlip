<?php
/**
 * Swiss Payment Slip
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright 2012-2015 Some nice Swiss guys
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Peter Siska <pesche@gridonic.ch>
 * @author Marc Würth ravage@bluewin.ch
 * @link https://github.com/sprain/class.Einzahlungsschein.php
 */

namespace SwissPaymentSlip\SwissPaymentSlip;

/**
 * Swiss Payment Slip Data
 *
 * Creates data containers for standard Swiss payment slips with or without reference number.
 * It doesn't actually do much. It's mostly a data container class to keep
 * including classes from having to care about how ESR work.
 * But it provides a flexibility of which data it holds, because not always
 * all slip fields are needed in an application.
 *
 * Glossary:
 * ESR = Einzahlungsschein mit Referenznummer
 *         ISR, (In-)Payment slip with reference number
 *         Summary term for orange payment slips in Switzerland
 * BESR = Banken-Einzahlungsschein mit Referenznummer
 *         Banking payment slip with reference number
 *         Orange payment slip for paying into a bank account (in contrast to a post cheque account with a VESR)
 * VESR = Verfahren für Einzahlungsschein mit Referenznummer
 *         Procedure for payment slip with reference number
 *         Orange payment slip for paying into a post cheque account (in contrast to a banking account with a BESR)
 * (B|V)ESR+ = Einzahlungsschein mit Referenznummer ohne Betragsangabe
 *         Payment slip with reference number without amount specification
 *         An payment slip can be issued without a predefined payment amount
 * ES = Einzahlungsschein
 *         IS, (In-)Payment slip
 *         Also summary term for all payment slips.
 *         Red payment slip for paying into a post cheque or bank account without reference number, with message box
 *
 * @link https://www.postfinance.ch/content/dam/pf/de/doc/consult/manual/dlserv/inpayslip_isr_man_de.pdf German manual
 * @link http://www.six-interbank-clearing.com/en/home/standardization/dta.html
 *
 * @todo Implement currency (CHF, EUR), means different prefixes in code line
 * @todo Implement payment on own account, means different prefixes in code line --> edge case!
 * @todo Implement cash on delivery (Nachnahme), means different prefixes in code line --> do it on demand
 * @todo Implement amount check for unrounded (.05) cents, document why (see manual)
 * @todo Create a getBankData method with formatting parameter, e.g. stripping blank lines
 * @todo Create a getRecipientData with formatting parameter, e.g. stripping blank lines
 */
abstract class PaymentSlipData
{

    /**
     * Consists the array table for calculating the check digit by modulo 10
     *
     * @var array Table for calculating the check digit by modulo 10.
     */
    private $moduloTable = array(0, 9, 4, 6, 8, 2, 7, 1, 3, 5);

    /**
     * Determines if the payment slip must not be used for payment (XXXed out)
     *
     * @var bool Normally false, true if not for payment.
     */
    protected $notForPayment = false;

    /**
     * Determines if the payment slip has a recipient bank. Can be disabled for pre-printed payment slips
     *
     * @var bool True if yes, false if no.
     */
    protected $withBank = true;

    /**
     * Determines if the payment slip has a account number. Can be disabled for pre-printed payment slips
     *
     * @var bool True if yes, false if no.
     */
    protected $withAccountNumber = true;

    /**
     * Determines if the payment slip has a recipient. Can be disabled for pre-printed payment slips
     *
     * @var bool True if yes, false if no.
     */
    protected $withRecipient = true;

    /**
     * Determines if it's an ESR or an ESR+
     *
     * @var bool True for ESR, false for ESR+.
     */
    protected $withAmount = true;

    /**
     * Determines if the payment slip has a payer. Can be disabled for pre-printed payment slips
     *
     * @var bool True if yes, false if no.
     */
    protected $withPayer = true;

    /**
     * The name of the bank
     *
     * @var string The name of the bank.
     */
    protected $bankName = '';

    /**
     * The postal code and city of the bank
     *
     * @var string The postal code and city of the bank.
     */
    protected $bankCity = '';

    /**
     * The bank or post cheque account where the money will be transferred to
     *
     * @var string The bank or post cheque account.
     */
    protected $accountNumber = '';

    /**
     * The first line of the recipient, e.g. "My Company Ltd."
     *
     * @var string The first line of the recipient.
     */
    protected $recipientLine1 = '';

    /**
     * The second line of the recipient, e.g. "Examplestreet 61"
     *
     * @var string The second line of the recipient.
     */
    protected $recipientLine2 = '';

    /**
     * The third line of the recipient, e.g. "8000 Zürich"
     *
     * @var string The third line of the recipient.
     */
    protected $recipientLine3 = '';

    /**
     * The fourth line of the recipient, if needed
     *
     * @var string The fourth line of the recipient.
     */
    protected $recipientLine4 = '';

    /**
     * The amount to be payed into. Can be disabled with withAmount = false for ESR+ slips
     *
     * @var float The amount to be payed into.
     */
    protected $amount = 0.0;

    /**
     * The first line of the payer, e.g. "Hans Mustermann"
     *
     * @var string The first line of the payer.
     */
    protected $payerLine1 = '';

    /**
     * The second line of the payer, e.g. "Main Street 11"
     *
     * @var string The second line of the payer.
     */
    protected $payerLine2 = '';

    /**
     * The third line of the payer, e.g. "4052 Basel"
     *
     * @var string The third line of the payer.
     */
    protected $payerLine3 = '';

    /**
     * The fourth line of the payer, if needed
     *
     * @var string The fourth line of the payer.
     */
    protected $payerLine4 = '';

    /**
     * Set payment slip for not to be used for payment
     *
     * XXXes out all fields to prevent people using the payment slip.
     *
     * @param boolean $notForPayment True if not for payment, else false.
     * @return $this The current instance for a fluent interface.
     */
    public function setNotForPayment($notForPayment = true)
    {
        $this->notForPayment = $notForPayment;

        if ($notForPayment === true) {
            $this->setBankData('XXXXXX', 'XXXXXX');
            $this->setAccountNumber('XXXXXX');
            $this->setRecipientData('XXXXXX', 'XXXXXX', 'XXXXXX', 'XXXXXX');
            $this->setPayerData('XXXXXX', 'XXXXXX', 'XXXXXX', 'XXXXXX');
            $this->setAmount('XXXXXXXX.XX');
        }

        return $this;
    }

    /**
     * Get whether this payment slip must not be used for payment
     *
     * @return bool True if yes, else false.
     */
    public function getNotForPayment()
    {
        return $this->notForPayment;
    }

    /**
     * Set if payment slip has a bank specified
     *
     * @param bool $withBank True for yes, false for no
     * @return $this The current instance for a fluent interface.
     */
    public function setWithBank($withBank = true)
    {
        if (is_bool($withBank)) {
            $this->withBank = $withBank;

            if (!$withBank) {
                $this->bankName = '';
                $this->bankCity = '';
            }
        }

        return $this;
    }

    /**
     * Get if payment slip has recipient specified
     *
     * @return bool True if payment slip has the recipient specified, else false.
     */
    public function getWithBank()
    {
        return $this->withBank;
    }

    /**
     * Set if payment slip has an account number specified
     *
     * @param bool $withAccountNumber True if yes, false if no.
     * @return $this The current instance for a fluent interface.
     */
    public function setWithAccountNumber($withAccountNumber = true)
    {
        if (is_bool($withAccountNumber)) {
            $this->withAccountNumber = $withAccountNumber;

            if (!$withAccountNumber) {
                $this->accountNumber = '';
            }
        }

        return $this;
    }

    /**
     * Get if payment slip has an account number specified
     *
     * @return bool True if payment slip has an account number specified, else false.
     */
    public function getWithAccountNumber()
    {
        return $this->withAccountNumber;
    }

    /**
     * Set if payment slip has a recipient specified
     *
     * @param bool $withRecipient True if yes, false if no.
     * @return $this The current instance for a fluent interface.
     */
    public function setWithRecipient($withRecipient = true)
    {
        if (is_bool($withRecipient)) {
            $this->withRecipient = $withRecipient;

            if (!$withRecipient) {
                $this->recipientLine1 = '';
                $this->recipientLine2 = '';
                $this->recipientLine3 = '';
                $this->recipientLine4 = '';
            }
        }

        return $this;
    }

    /**
     * Get if payment slip has a recipient specified
     *
     * @return bool True if payment slip has a recipient specified, else false.
     */
    public function getWithRecipient()
    {
        return $this->withRecipient;
    }

    /**
     * Set if payment slip has an amount specified
     *
     * @param bool $withAmount True for yes, false for no.
     * @return $this The current instance for a fluent interface.
     */
    public function setWithAmount($withAmount = true)
    {
        if (is_bool($withAmount)) {
            $this->withAmount = $withAmount;

            if (!$withAmount) {
                $this->amount = 0.0;
            }
        }

        return $this;
    }

    /**
     * Get if payment slip has an amount specified
     *
     * @return bool True if payment slip has an amount specified, else false.
     */
    public function getWithAmount()
    {
        return $this->withAmount;
    }

    /**
     * Set if payment slip has a payer specified
     *
     * @param bool $withPayer True if yes, false if no.
     * @return $this The current instance for a fluent interface.
     */
    public function setWithPayer($withPayer = true)
    {
        if (is_bool($withPayer)) {
            $this->withPayer = $withPayer;

            if (!$withPayer) {
                $this->payerLine1 = '';
                $this->payerLine2 = '';
                $this->payerLine3 = '';
                $this->payerLine4 = '';
            }
        }

        return $this;
    }

    /**
     * Get if payment slip has a payer specified
     *
     * @return bool True if payment slip has a payer specified, else false.
     */
    public function getWithPayer()
    {
        return $this->withPayer;
    }

    /**
     * Sets the name, city and account number of the bank
     *
     * @param string $bankName Name of the bank.
     * @param string $bankCity City of the bank.
     * @return $this The current instance for a fluent interface.
     */
    public function setBankData($bankName, $bankCity)
    {
        $this->setBankName($bankName);
        $this->setBankCity($bankCity);

        return $this;
    }

    /**
     * Set the name of the bank
     *
     * @param string $bankName The name of the bank.
     * @return $this The current instance for a fluent interface.
     *
     * @todo Implement max length check
     */
    protected function setBankName($bankName)
    {
        if ($this->getWithBank()) {
            $this->bankName = $bankName;
        }

        return $this;
    }

    /**
     * Get the name of the bank
     *
     * @return string|bool The name of the bank or false if withBank = false.
     */
    public function getBankName()
    {
        if ($this->getWithBank()) {
            return $this->bankName;
        }
        return false;
    }

    /**
     * Set the postal code and city of the bank
     *
     * @param string $bankCity The postal code and city of the bank
     * @return $this The current instance for a fluent interface.
     *
     * @todo Implement max length check
     */
    protected function setBankCity($bankCity)
    {
        if ($this->getWithBank()) {
            $this->bankCity = $bankCity;
        }

        return $this;
    }

    /**
     * Get the postal code and city of the bank
     *
     * @return string|bool The postal code and city of the bank or false if withBank = false.
     */
    public function getBankCity()
    {
        if ($this->getWithBank()) {
            return $this->bankCity;
        }
        return false;
    }

    /**
     * Set the bank or post cheque account where the money will be transferred to
     *
     * @param string $accountNumber The bank or post cheque account.
     * @return $this The current instance for a fluent interface.
     *
     * @todo Implement parameter validation (two hyphens, min & max length)
     */
    public function setAccountNumber($accountNumber)
    {
        if ($this->getWithAccountNumber()) {
            $this->accountNumber = $accountNumber;
        }

        return $this;
    }

    /**
     * Get the bank or post cheque account where the money will be transferred to
     *
     * @return string|bool The bank or post cheque account or false if withAccountNumber = false.
     */
    public function getAccountNumber()
    {
        if ($this->getWithAccountNumber()) {
            return $this->accountNumber;
        }
        return false;
    }

    /**
     * Sets the four lines of the recipient
     *
     * @param string $recipientLine1 The first line of the recipient, e.g. "My Company Ltd.".
     * @param string $recipientLine2 The second line of the recipient, e.g. "Examplestreet 61".
     * @param string $recipientLine3 The third line of the recipient, e.g. "8000 Zürich".
     * @param string $recipientLine4 The fourth line of the recipient, if needed.
     * @return $this The current instance for a fluent interface.
     */
    public function setRecipientData($recipientLine1, $recipientLine2, $recipientLine3 = '', $recipientLine4 = '')
    {
        $this->setRecipientLine1($recipientLine1);
        $this->setRecipientLine2($recipientLine2);
        $this->setRecipientLine3($recipientLine3);
        $this->setRecipientLine4($recipientLine4);

        return $this;
    }

    /**
     * Set the first line of the recipient
     *
     * @param string $recipientLine1 The first line of the recipient, e.g. "My Company Ltd.".
     * @return $this The current instance for a fluent interface.
     */
    protected function setRecipientLine1($recipientLine1)
    {
        if ($this->getWithRecipient()) {
            $this->recipientLine1 = $recipientLine1;
        }

        return $this;
    }

    /**
     * Get the first line of the recipient
     *
     * @return string|bool The first line of the recipient or false if withRecipient = false.
     */
    public function getRecipientLine1()
    {
        if ($this->getWithRecipient()) {
            return $this->recipientLine1;
        }
        return false;
    }

    /**
     * Set the second line of the recipient
     *
     * @param string $recipientLine2 The second line of the recipient, e.g. "Examplestreet 61".
     * @return $this The current instance for a fluent interface.
     */
    protected function setRecipientLine2($recipientLine2)
    {
        if ($this->getWithRecipient()) {
            $this->recipientLine2 = $recipientLine2;
        }

        return $this;
    }

    /**
     * Get the second line of the recipient
     *
     * @return string|bool The second line of the recipient or false if withRecipient = false.
     */
    public function getRecipientLine2()
    {
        if ($this->getWithRecipient()) {
            return $this->recipientLine2;
        }
        return false;
    }

    /**
     * Set the third line of the recipient
     *
     * @param string $recipientLine3 The third line of the recipient, e.g. "8000 Zürich".
     * @return $this The current instance for a fluent interface.
     */
    protected function setRecipientLine3($recipientLine3)
    {
        if ($this->getWithRecipient()) {
            $this->recipientLine3 = $recipientLine3;
        }

        return $this;
    }

    /**
     * Get the third line of the recipient
     *
     * @return string|bool The third line of the recipient or false if withRecipient = false.
     */
    public function getRecipientLine3()
    {
        if ($this->getWithRecipient()) {
            return $this->recipientLine3;
        }
        return false;
    }

    /**
     * Set the fourth line of the recipient
     *
     * @param string $recipientLine4 The fourth line of the recipient, if needed.
     * @return $this The current instance for a fluent interface.
     */
    protected function setRecipientLine4($recipientLine4)
    {
        if ($this->getWithRecipient()) {
            $this->recipientLine4 = $recipientLine4;
        }

        return $this;
    }

    /**
     * Get the fourth line of the recipient
     *
     * @return string|bool The fourth line of the recipient or false if withRecipient = false.
     */
    public function getRecipientLine4()
    {
        if ($this->getWithRecipient()) {
            return $this->recipientLine4;
        }
        return false;
    }

    /**
     * Set the amount of the payment slip. Only possible if it's not a ESR+.
     *
     * @param float $amount The amount to be payed into
     * @return $this The current instance for a fluent interface.
     */
    public function setAmount($amount = 0.0)
    {
        if ($this->getWithAmount()) {
            $this->amount = $amount;
        }

        return $this;
    }

    /**
     * Get the amount to be payed into
     *
     * @return float The amount to be payed into.
     */
    public function getAmount()
    {
        if ($this->getWithAmount()) {
            return $this->amount;
        }
        return false;
    }

    /**
     * Sets the four lines of the payer
     *
     * At least two lines are necessary.
     *
     * @param string $payerLine1 The first line of the payer, e.g. "Hans Mustermann".
     * @param string $payerLine2 The second line of the payer, e.g. "Main Street 11".
     * @param string $payerLine3 The third line of the payer, e.g. "4052 Basel".
     * @param string $payerLine4 The fourth line of the payer, if needed.
     * @return $this The current instance for a fluent interface.
     */
    public function setPayerData($payerLine1, $payerLine2, $payerLine3 = '', $payerLine4 = '')
    {
        $this->setPayerLine1($payerLine1);
        $this->setPayerLine2($payerLine2);
        $this->setPayerLine3($payerLine3);
        $this->setPayerLine4($payerLine4);

        return $this;
    }

    /**
     * Set the first line of the payer
     *
     * @param string $payerLine1 The first line of the payer, e.g. "Hans Mustermann".
     * @return $this The current instance for a fluent interface.
     */
    protected function setPayerLine1($payerLine1)
    {
        if ($this->getWithPayer()) {
            $this->payerLine1 = $payerLine1;
        }

        return $this;
    }

    /**
     * Get the first line of the payer
     *
     * @return string|bool The first line of the payer or false if withPayer = false.
     */
    public function getPayerLine1()
    {
        if ($this->getWithPayer()) {
            return $this->payerLine1;
        }
        return false;
    }

    /**
     * Set the second line of the payer
     *
     * @param string $payerLine2 The second line of the payer, e.g. "Main Street 11".
     * @return $this The current instance for a fluent interface.
     */
    protected function setPayerLine2($payerLine2)
    {
        if ($this->getWithPayer()) {
            $this->payerLine2 = $payerLine2;
        }

        return $this;
    }

    /**
     * Get the second line of the payer
     *
     * @return string|bool The second line of the payer or false if withPayer = false.
     */
    public function getPayerLine2()
    {
        if ($this->getWithPayer()) {
            return $this->payerLine2;
        }
        return false;
    }

    /**
     * Set the third line of the payer
     *
     * @param string $payerLine3 The third line of the payer, e.g. "4052 Basel".
     * @return $this The current instance for a fluent interface.
     */
    protected function setPayerLine3($payerLine3)
    {
        if ($this->getWithPayer()) {
            $this->payerLine3 = $payerLine3;
        }

        return $this;
    }

    /**
     * Get the third line of the payer
     *
     * @return string|bool The third line of the payer or false if withPayer = false.
     */
    public function getPayerLine3()
    {
        if ($this->getWithPayer()) {
            return $this->payerLine3;
        }
        return false;
    }

    /**
     * Set the fourth line of the payer
     *
     * @param string $payerLine4 The fourth line of the payer, if needed.
     * @return $this The current instance for a fluent interface.
     */
    protected function setPayerLine4($payerLine4)
    {
        if ($this->getWithPayer()) {
            $this->payerLine4 = $payerLine4;
        }

        return $this;
    }

    /**
     * Get the fourth line of the payer
     *
     * @return string|bool The fourth line of the payer or false if withPayer = false.
     */
    public function getPayerLine4()
    {
        if ($this->getWithPayer()) {
            return $this->payerLine4;
        }
        return false;
    }

    /**
     * Clear the account of the two hyphens
     *
     * @return string|false The account of the two hyphens, 'XXXXXXXXX' if not for payment or else false.
     */
    protected function getAccountDigits()
    {
        if ($this->getWithAccountNumber()) {
            if ($this->getNotForPayment()) {
                return 'XXXXXXXXX';
            }
            $accountNumber = $this->getAccountNumber();
            if ($accountNumber) {
                $accountDigits = str_replace('-', '', $accountNumber, $replacedHyphens);
                if ($replacedHyphens == 2) {
                    return $accountDigits;
                }
            }
        }
        return false;
    }

    /**
     * Returns francs amount without cents
     *
     * @return bool|int Francs amount without cents.
     */
    public function getAmountFrancs()
    {
        $amount = $this->getAmount();
        if ($this->getNotForPayment()) {
            return 'XXXXXXXX';
        }
        if ($amount === false) {
            return false;
        }
        $francs = intval($amount);
        return $francs;
    }

    /**
     * Returns zero filled, right padded, two digits long cents amount
     *
     * @return bool|string Amount of Cents, zero filled, right padded, two digits long.
     */
    public function getAmountCents()
    {
        $amount = $this->getAmount();
        if ($this->getNotForPayment()) {
            return 'XX';
        }
        if ($amount === false) {
            return false;
        }
        $francs = intval($amount);
        $cents = round(($amount - $francs) * 100);
        return str_pad($cents, 2, '0', STR_PAD_RIGHT);
    }

    /**
     * Creates Modulo10 recursive check digit
     *
     * @copyright As found on http://www.developers-guide.net/forums/5431,modulo10-rekursiv (thanks, dude!)
     * @param string $number Number to create recursive check digit off.
     * @return int Recursive check digit.
     */
    protected function modulo10($number)
    {
        $next = 0;
        for ($i=0; $i < strlen($number); $i++) {
            $next = $this->moduloTable[($next + substr($number, $i, 1)) % 10];
        }

        return (10 - $next) % 10;
    }

    /**
     * Returns a given string in blocks of a certain size
     * Example: 000000000000000 becomes more readable 00000 00000 00000
     *
     * @param string $string To be formatted string.
     * @param int $blockSize Block size of choice.
     * @param bool $alignFromRight Right aligned, blocks are build from right.
     * @return string Given string divided in blocks of given block size separated by one space.
     */
    protected function breakStringIntoBlocks($string, $blockSize = 5, $alignFromRight = true)
    {
        // Lets reverse the string (because we want the block to be aligned from the right)
        if ($alignFromRight) {
            $string = strrev($string);
        }

        // Chop it into blocks
        $string = trim(chunk_split($string, $blockSize, ' '));

        // Re-reverse
        if ($alignFromRight) {
            $string = strrev($string);
        }

        return $string;
    }

    /**
     * Get the full code line at the bottom of the ES
     *
     * Needs to be implemented by each slip data sub class.
     *
     * @param bool $fillZeros Fill up with leading zeros.
     * @return string The full code line.
     */
    abstract public function getCodeLine($fillZeros = true);
}
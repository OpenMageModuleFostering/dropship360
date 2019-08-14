<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Ediidentifier
 *
 * @author shubhs
 */
class Logicbroker_Dropship360_Model_System_Config_Source_Ediqualifier {
    
     
    public function toOptionArray()
    {
        return array(
            array('value' => '', 'label' => Mage::helper('logicbroker')->__('--Please Select--')),
            array('value' => Mage::helper('logicbroker')->__('01 Duns (Dun & Bradstreet)'), 'label' => Mage::helper('logicbroker')->__('01 Duns (Dun & Bradstreet)')),
            array('value' => Mage::helper('logicbroker')->__('02 SCAC (Standard Carrier Alpha Code)'), 'label' => Mage::helper('logicbroker')->__('02 SCAC (Standard Carrier Alpha Code)')),
            array('value' => Mage::helper('logicbroker')->__('03 FMC (Federal Maritime Commission)'), 'label' => Mage::helper('logicbroker')->__('03 FMC (Federal Maritime Commission)')),
            array('value' => Mage::helper('logicbroker')->__('04 IATA (International Air Transport Association)'), 'label' => Mage::helper('logicbroker')->__('04 IATA (International Air Transport Association)')),
            array('value' => Mage::helper('logicbroker')->__('08 UCC EDI Communications ID (Comm ID)'), 'label' => Mage::helper('logicbroker')->__('08 UCC EDI Communications ID (Comm ID)')),
            array('value' => Mage::helper('logicbroker')->__('09 X.121 (CCITT)'), 'label' => Mage::helper('logicbroker')->__('09 X.121 (CCITT)')),
            array('value' => Mage::helper('logicbroker')->__('10 Department of Defence (DoD) Activity Address Code'), 'label' => Mage::helper('logicbroker')->__('10 Department of Defence (DoD) Activity Address Code')),
            array('value' => Mage::helper('logicbroker')->__('11 DEA (Drug Enforcement Administration)'), 'label' => Mage::helper('logicbroker')->__('11 DEA (Drug Enforcement Administration)')),
            array('value' => Mage::helper('logicbroker')->__('12 Phone (Telephone Companies)'), 'label' => Mage::helper('logicbroker')->__('12 Phone (Telephone Companies)')),
            array('value' => Mage::helper('logicbroker')->__('13 UCS Code (UCS Code is a Code is Used for UCS Transmissions, it includes the Area Code and Telephone Number of Modem, it Does Not Include Punctuation, Blanks or Access Code )'), 'label' => Mage::helper('logicbroker')->__('13 UCS Code (UCS Code is a Code is Used for UCS Transmissions, it includes the Area Code and Telephone Number of Modem, it Does Not Include Punctuation, Blanks or Access Code )')),
            array('value' => Mage::helper('logicbroker')->__('14 Duns Plus Suffix'), 'label' => Mage::helper('logicbroker')->__('14 Duns Plus Suffix')),
            array('value' => Mage::helper('logicbroker')->__('15 Petroleum Accountants Society Of Canada Company Code'), 'label' => Mage::helper('logicbroker')->__('15 Petroleum Accountants Society Of Canada Company Code')),
            array('value' => Mage::helper('logicbroker')->__('16 Duns Number With 4-Character Suffix'), 'label' => Mage::helper('logicbroker')->__('16 Duns Number With 4-Character Suffix')),
            array('value' => Mage::helper('logicbroker')->__('17 American Bankers Association (ABA) Transit Routing Number (Including check digit, 9-digit )'), 'label' => Mage::helper('logicbroker')->__('17 American Bankers Association (ABA) Transit Routing Number (Including check digit, 9-digit )')),
            array('value' => Mage::helper('logicbroker')->__('18 Association of American Railroads (AAR) Standard Distribution Code'), 'label' => Mage::helper('logicbroker')->__('18 Association of American Railroads (AAR) Standard Distribution Code')),
            array('value' => Mage::helper('logicbroker')->__('19 EDI Council of Australia (EDICA) Communications ID Number (COMM ID)'), 'label' => Mage::helper('logicbroker')->__('19 EDI Council of Australia (EDICA) Communications ID Number (COMM ID)')),
            array('value' => Mage::helper('logicbroker')->__('20 Health Industry Number (HIN)'), 'label' => Mage::helper('logicbroker')->__('20 Health Industry Number (HIN)')),
            array('value' => Mage::helper('logicbroker')->__('21 Integrated Postsecondary Education Data system, or (IPEDS)'), 'label' => Mage::helper('logicbroker')->__('21 Integrated Postsecondary Education Data system, or (IPEDS)')),
            array('value' => Mage::helper('logicbroker')->__('22 Federal Interagency Commision on Education, or FICE'), 'label' => Mage::helper('logicbroker')->__('22 Federal Interagency Commision on Education, or FICE')),
            array('value' => Mage::helper('logicbroker')->__('23 National Center for Education Statistics Common Core Of Data 12-Digit Number For Pre-K-Grade 12 Institutes, or NCES'), 'label' => Mage::helper('logicbroker')->__('23 National Center for Education Statistics Common Core Of Data 12-Digit Number For Pre-K-Grade 12 Institutes, or NCES')),
            array('value' => Mage::helper('logicbroker')->__('24 The College Board`s Admission Testing Program 4-Digit Code Of Postsecondary Institutes, or ATP'), 'label' => Mage::helper('logicbroker')->__('24 The College Board`s Admission Testing Program 4-Digit Code Of Postsecondary Institutes, or ATP')),
            array('value' => Mage::helper('logicbroker')->__('25 American College Testing Program 4-Digit code Of Postsecondary Institutions, or ACT'), 'label' => Mage::helper('logicbroker')->__('25 American College Testing Program 4-Digit code Of Postsecondary Institutions, or ACT')),
            array('value' => Mage::helper('logicbroker')->__('26 Statistics of Canada List Of Postsecondary Institutions'), 'label' => Mage::helper('logicbroker')->__('26 Statistics of Canada List Of Postsecondary Institutions')),
            array('value' => Mage::helper('logicbroker')->__('27 Carrier Identification Number as assigned by Health Care Financing Administration (HCFA)'), 'label' => Mage::helper('logicbroker')->__('27 Carrier Identification Number as assigned by Health Care Financing Administration (HCFA)')),
            array('value' => Mage::helper('logicbroker')->__('28 Fiscal Intermediary Identification Number as assigned by Health Care Financing Administration (HCFA)'), 'label' => Mage::helper('logicbroker')->__('28 Fiscal Intermediary Identification Number as assigned by Health Care Financing Administration (HCFA)')),
            array('value' => Mage::helper('logicbroker')->__('29 Medicare Provider and Supplier Identification Number as assigned by Health Care Financing Administration (HCFA)'), 'label' => Mage::helper('logicbroker')->__('29 Medicare Provider and Supplier Identification Number as assigned by Health Care Financing Administration (HCFA)')),
            array('value' => Mage::helper('logicbroker')->__('30 U.S. Federal Tax Identification Number'), 'label' => Mage::helper('logicbroker')->__('30 U.S. Federal Tax Identification Number')),
            array('value' => Mage::helper('logicbroker')->__('31 Jurisdiction Identification Number Plus 4 as assigned by the International Association Of Industrial Accident Boards and Commissions (IAIABC)'), 'label' => Mage::helper('logicbroker')->__('31 Jurisdiction Identification Number Plus 4 as assigned by the International Association Of Industrial Accident Boards and Commissions (IAIABC)')),
            array('value' => Mage::helper('logicbroker')->__('32 U.S. Federal Employer Identification Number (FEIN)'), 'label' => Mage::helper('logicbroker')->__('32 U.S. Federal Employer Identification Number (FEIN)')),
            array('value' => Mage::helper('logicbroker')->__('33 National Association Of Insurance Commissioners Company Code (NAIC)'), 'label' => Mage::helper('logicbroker')->__('33 National Association Of Insurance Commissioners Company Code (NAIC)')),
            array('value' => Mage::helper('logicbroker')->__('34 Medicaid Provider and Supplier Identification Number as assigned by individual State Medicaid Agencies in conjunction with Health Care Financing Administration (HCFA)'), 'label' => Mage::helper('logicbroker')->__('34 Medicaid Provider and Supplier Identification Number as assigned by individual State Medicaid Agencies in Conjunction with Health Care Financing Administration (HCFA)')),
            array('value' => Mage::helper('logicbroker')->__('35 Statistics Canada Canadian College Student Information System Institution Codes'), 'label' => Mage::helper('logicbroker')->__('35 Statistics Canada Canadian College Student Information System Institution Codes')),
            array('value' => Mage::helper('logicbroker')->__('36 Statistics Canada University Student Information System Institution codes'), 'label' => Mage::helper('logicbroker')->__('36 Statistics Canada University Student Information System Institution codes')),
            array('value' => Mage::helper('logicbroker')->__('37 Society of Property Information Compilers and Analysts'), 'label' => Mage::helper('logicbroker')->__('37 Society of Property Information Compilers and Analysts')),
            array('value' => Mage::helper('logicbroker')->__('AM Association Mexicana del Codigo de Producto (AMECOP) Communication ID'), 'label' => Mage::helper('logicbroker')->__('AM Association Mexicana del Codigo de Producto (AMECOP) Communication ID')),
            array('value' => Mage::helper('logicbroker')->__('NR National Retail Merchants Association (NRMA) - Assigned'), 'label' => Mage::helper('logicbroker')->__('NR National Retail Merchants Association (NRMA) - Assigned')),
            array('value' => Mage::helper('logicbroker')->__('SN Standard Address Number'), 'label' => Mage::helper('logicbroker')->__('SN Standard Address Number')),
            array('value' => Mage::helper('logicbroker')->__('ZZ Mutually Defined'), 'label' => Mage::helper('logicbroker')->__('ZZ Mutually Defined')),
        );
    }
    
     public function toArray()
    {
        return array(
            0 => Mage::helper('adminhtml')->__('No'),
            1 => Mage::helper('adminhtml')->__('Yes'),
        );
    }
}
